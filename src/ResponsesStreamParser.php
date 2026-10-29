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
        $completed = false;
        $responseIdentity = new \AiSdk\Responses\ResponseMetadata();

        /** @var array<int, string> $functionArguments */
        $functionArguments = [];

        /** @var array<int, bool> $functionCallsStarted */
        $functionCallsStarted = [];

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
                throw \AiSdk\Utils\Errors\HttpErrorNormalizer::fromStreamError($providerName, $payload['error'] ?? $payload['response']['error'] ?? null);
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
                $index = (int) ($payload['output_index'] ?? 0);
                $functionCallsStarted[$index] = true;

                yield new ToolCallStartPart(
                    index: $index,
                    id: (string) ($payload['item']['call_id'] ?? $payload['item']['id'] ?? ''),
                    name: (string) ($payload['item']['name'] ?? ''),
                );
            }

            if ($type === 'response.function_call_arguments.delta' && is_string($payload['delta'] ?? null)) {
                $index = (int) ($payload['output_index'] ?? 0);
                $functionArguments[$index] = ($functionArguments[$index] ?? '') . $payload['delta'];

                yield new ToolCallDeltaPart(
                    index: $index,
                    argsJson: $payload['delta'],
                    id: isset($payload['item_id']) ? (string) $payload['item_id'] : null,
                );
            }

            if ($type === 'response.output_item.done' && is_array($payload['item'] ?? null) && ($payload['item']['type'] ?? null) === 'function_call') {
                $item = $payload['item'];
                $index = (int) ($payload['output_index'] ?? 0);

                if (! isset($functionCallsStarted[$index])) {
                    $functionCallsStarted[$index] = true;
                    yield new ToolCallStartPart(
                        index: $index,
                        id: (string) ($item['call_id'] ?? $item['id'] ?? ''),
                        name: (string) ($item['name'] ?? ''),
                    );
                }

                $arguments = $item['arguments'] ?? null;
                if (is_string($arguments) && $arguments !== '' && ($functionArguments[$index] ?? '') === '') {
                    yield new ToolCallDeltaPart(
                        index: $index,
                        argsJson: $arguments,
                        id: (string) ($item['call_id'] ?? $item['id'] ?? ''),
                        name: (string) ($item['name'] ?? ''),
                    );
                }
            }

            if (in_array($type, ['response.completed', 'response.incomplete'], true) && is_array($payload['response'] ?? null)) {
                $completed = true;
                $response = $payload['response'];
                $finishReason = ResponsesResponseParser::finishReason($response);
                $raw = $response['incomplete_details']['reason'] ?? $response['status'] ?? null;
                $responseIdentity = new \AiSdk\Responses\ResponseMetadata(
                    is_string($response['id'] ?? null) ? $response['id'] : null,
                    is_string($response['model'] ?? null) ? $response['model'] : null,
                    is_string($raw) ? $raw : null,
                );

                if (isset($response['usage']) && is_array($response['usage'])) {
                    $usage = ResponsesUsage::fromArray($response['usage']);
                }
            }
        }

        if (! $completed) {
            throw InvalidResponseException::forProvider($providerName, "Provider [{$providerName}] ended its Responses stream before completing; the response may be incomplete.");
        }

        yield new FinishPart($finishReason, $usage, $responseIdentity);
    }
}
