<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\FinishReason;
use AiSdk\OpenAICompatible\Support\ResponsesUsage;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\ProviderMetadataPart;
use AiSdk\Streaming\ReasoningDeltaPart;
use AiSdk\Streaming\StreamPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallDeltaPart;
use AiSdk\Streaming\ToolCallStartPart;
use AiSdk\Support\Usage;
use Generator;

final class ResponsesStreamParser
{
    /**
     * @param  iterable<int, array{event: ?string, data: string}>  $events
     * @return Generator<int, StreamPart>
     */
    public static function parse(iterable $events, string $providerName): Generator
    {
        $usage = Usage::empty();
        $finishReason = FinishReason::Unknown;
        $metadataEmitted = false;

        foreach ($events as $event) {
            if ($event['data'] === '' || $event['data'] === '[DONE]') {
                continue;
            }

            $payload = json_decode($event['data'], true);
            if (! is_array($payload)) {
                throw InvalidResponseException::forProvider($providerName, "Provider [{$providerName}] returned invalid JSON in its Responses stream.", ['body' => $event['data']]);
            }

            $type = (string) ($payload['type'] ?? $event['event'] ?? '');

            if ($type === 'error' || $type === 'response.failed') {
                $error = $payload['error'] ?? $payload['response']['error'] ?? null;
                $message = is_array($error) && is_string($error['message'] ?? null)
                    ? $error['message']
                    : "Provider [{$providerName}] returned a Responses stream error.";

                throw InvalidResponseException::forProvider($providerName, $message, ['body' => $payload]);
            }

            if (! $metadataEmitted && isset($payload['response']) && is_array($payload['response'])) {
                $response = $payload['response'];
                $metadata = array_filter([
                    'id' => $response['id'] ?? null,
                    'model' => $response['model'] ?? null,
                    'status' => $response['status'] ?? null,
                ], fn(mixed $value): bool => $value !== null);

                if ($metadata !== []) {
                    $metadataEmitted = true;
                    yield new ProviderMetadataPart($providerName, $metadata);
                }
            }

            if ($type === 'response.output_text.delta' && is_string($payload['delta'] ?? null)) {
                yield new TextDeltaPart($payload['delta']);
            }

            if (in_array($type, ['response.reasoning_text.delta', 'response.reasoning_summary_text.delta'], true) && is_string($payload['delta'] ?? null)) {
                yield new ReasoningDeltaPart($payload['delta']);
            }

            if ($type === 'response.output_item.added' && is_array($payload['item'] ?? null) && ($payload['item']['type'] ?? null) === 'function_call') {
                yield new ToolCallStartPart(
                    index: (int) ($payload['output_index'] ?? 0),
                    id: (string) ($payload['item']['call_id'] ?? $payload['item']['id'] ?? ''),
                    name: (string) ($payload['item']['name'] ?? ''),
                );
            }

            if ($type === 'response.function_call_arguments.delta' && is_string($payload['delta'] ?? null)) {
                yield new ToolCallDeltaPart(
                    index: (int) ($payload['output_index'] ?? 0),
                    argsJson: $payload['delta'],
                    id: isset($payload['item_id']) ? (string) $payload['item_id'] : null,
                );
            }

            if (in_array($type, ['response.completed', 'response.incomplete'], true) && is_array($payload['response'] ?? null)) {
                $response = $payload['response'];
                $finishReason = ResponsesResponseParser::finishReason($response);

                if (isset($response['usage']) && is_array($response['usage'])) {
                    $usage = ResponsesUsage::fromArray($response['usage']);
                }
            }
        }

        yield new FinishPart($finishReason, $usage);
    }
}
