<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\OpenAICompatible\Converters\MapsFinishReason;
use AiSdk\OpenAICompatible\Support\ChatUsage;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\ProviderMetadataPart;
use AiSdk\Streaming\ReasoningDeltaPart;
use AiSdk\Streaming\StreamPart;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Streaming\ToolCallDeltaPart;
use AiSdk\Streaming\ToolCallStartPart;
use AiSdk\Support\Usage;
use Generator;

/**
 * Converts an OpenAI-compatible SSE event stream to typed StreamParts.
 * Tool-call fragments carry their slot index so core accumulates correctly.
 */
final class ChatStreamParser
{
    /**
     * @param  iterable<int, array{event: ?string, data: string}>  $events
     * @return Generator<int, StreamPart>
     */
    public static function parse(iterable $events, string $providerName): Generator
    {
        $usage = Usage::empty();
        $finishReason = null;
        $emittedResponseMetadata = false;

        /** @var array<int, bool> $started */
        $started = [];

        foreach ($events as $event) {
            $data = $event['data'];
            if ($data === '' || $data === '[DONE]') {
                continue;
            }

            $payload = json_decode($data, true);
            if (! is_array($payload)) {
                throw InvalidResponseException::forProvider(
                    $providerName,
                    "Provider [{$providerName}] returned invalid JSON in its event stream.",
                    ['body' => $data],
                );
            }

            $error = $payload['error'] ?? null;
            if (is_array($error) || is_string($error)) {
                $message = is_array($error) && is_string($error['message'] ?? null)
                    ? $error['message']
                    : (is_string($error) ? $error : "Provider [{$providerName}] returned a stream error.");

                throw InvalidResponseException::forProvider($providerName, $message, ['body' => $payload]);
            }

            if (isset($payload['usage']) && is_array($payload['usage'])) {
                $usage = ChatUsage::fromArray($payload['usage']);
            }

            if (! $emittedResponseMetadata) {
                $metadata = self::responseMetadata($payload);
                if ($metadata !== []) {
                    $emittedResponseMetadata = true;
                    yield new ProviderMetadataPart($providerName, $metadata);
                }
            }

            $choice = $payload['choices'][0] ?? null;
            if ($choice === null) {
                if (! isset($payload['usage'])) {
                    throw InvalidResponseException::forProvider(
                        $providerName,
                        "Provider [{$providerName}] returned a stream event without choices or usage.",
                        ['body' => $payload],
                    );
                }

                continue;
            }

            if (! is_array($choice)) {
                throw InvalidResponseException::forProvider($providerName, "Provider [{$providerName}] returned an invalid stream choice.", ['body' => $payload]);
            }

            $delta = $choice['delta'] ?? [];

            if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
                yield new TextDeltaPart($delta['content']);
            }

            $reasoning = $delta['reasoning_content'] ?? $delta['reasoning'] ?? null;
            if (is_string($reasoning) && $reasoning !== '') {
                yield new ReasoningDeltaPart($reasoning);
            }

            foreach (($delta['tool_calls'] ?? []) as $call) {
                $index = (int) ($call['index'] ?? 0);

                if (! isset($started[$index]) && isset($call['id'])) {
                    $started[$index] = true;
                    yield new ToolCallStartPart(
                        index: $index,
                        id: (string) $call['id'],
                        name: (string) ($call['function']['name'] ?? ''),
                    );
                }

                $args = $call['function']['arguments'] ?? '';
                if (is_string($args) && $args !== '') {
                    yield new ToolCallDeltaPart(
                        index: $index,
                        argsJson: $args,
                        id: isset($call['id']) ? (string) $call['id'] : null,
                        name: isset($call['function']['name']) ? (string) $call['function']['name'] : null,
                    );
                }
            }

            if (isset($choice['finish_reason'])) {
                $finishReason = MapsFinishReason::map((string) $choice['finish_reason']);
                yield new ProviderMetadataPart($providerName, self::choiceMetadata($choice));
            }
        }

        yield new FinishPart($finishReason ?? MapsFinishReason::map(null), $usage);
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function responseMetadata(array $payload): array
    {
        $metadata = [];

        foreach (['id', 'object', 'created', 'model', 'system_fingerprint', 'service_tier'] as $key) {
            if (array_key_exists($key, $payload)) {
                $metadata[$key] = $payload[$key];
            }
        }

        return $metadata;
    }

    /**
     * @param  array<int|string, mixed>  $choice
     * @return array<string, mixed>
     */
    private static function choiceMetadata(array $choice): array
    {
        $metadata = [];

        foreach (['index', 'finish_reason'] as $key) {
            if (array_key_exists($key, $choice)) {
                $metadata["choice_{$key}"] = $choice[$key];
            }
        }

        return $metadata;
    }
}
