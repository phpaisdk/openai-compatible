<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Responses\EmbeddingResponse;
use AiSdk\Results\EmbeddingData;
use AiSdk\Support\Usage;

final class EmbeddingResponseParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function parse(array $payload, string $providerName, ?int $expectedCount = null): EmbeddingResponse
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data) || ! array_is_list($data) || $data === []) {
            self::throwInvalidResponse($payload, $providerName);
        }

        $embeddings = [];

        foreach ($data as $entry) {
            if (! is_array($entry) || ! is_array($entry['embedding'] ?? null)) {
                self::throwInvalidResponse($payload, $providerName);
            }

            $vector = [];
            foreach ($entry['embedding'] as $value) {
                if (! is_int($value) && ! is_float($value)) {
                    $vector = [];
                    break;
                }

                $vector[] = (float) $value;
            }

            if ($vector === []) {
                self::throwInvalidResponse($payload, $providerName);
            }

            $embeddings[] = new EmbeddingData(
                vector: $vector,
                index: is_int($entry['index'] ?? null) ? $entry['index'] : null,
            );
        }

        if ($expectedCount !== null && count($embeddings) !== $expectedCount) {
            self::throwInvalidResponse($payload, $providerName, 'an unexpected number of embeddings');
        }

        $indices = array_map(static fn(EmbeddingData $embedding): ?int => $embedding->index, $embeddings);
        if ($expectedCount !== null && in_array(null, $indices, true)) {
            self::throwInvalidResponse($payload, $providerName, 'invalid embedding indices');
        }

        if (! in_array(null, $indices, true)) {
            sort($indices);
            if ($indices !== range(0, count($embeddings) - 1)) {
                self::throwInvalidResponse($payload, $providerName, 'invalid embedding indices');
            }

            usort(
                $embeddings,
                static fn(EmbeddingData $left, EmbeddingData $right): int => $left->index <=> $right->index,
            );
        }

        $usageData = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $totalTokens = self::intValue($usageData, 'total_tokens', 'totalTokens');
        $inputTokens = self::intValue($usageData, 'prompt_tokens', 'input_tokens', 'promptTokens', 'inputTokens')
            ?? $totalTokens
            ?? 0;

        return new EmbeddingResponse(
            embeddings: $embeddings,
            usage: new Usage(
                inputTokens: $inputTokens,
                totalTokens: $totalTokens,
            ),
            rawResponse: $payload,
            providerMetadata: [
                $providerName => array_filter([
                    'id' => is_string($payload['id'] ?? null) ? $payload['id'] : null,
                    'model' => is_string($payload['model'] ?? null) ? $payload['model'] : null,
                    'object' => is_string($payload['object'] ?? null) ? $payload['object'] : null,
                ], static fn(mixed $value): bool => $value !== null),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function intValue(array $values, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (is_numeric($values[$key] ?? null)) {
                return (int) $values[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function throwInvalidResponse(
        array $payload,
        string $providerName,
        string $reason = 'no valid embeddings',
    ): never {
        throw InvalidResponseException::forProvider(
            $providerName,
            "Provider [{$providerName}] returned {$reason}.",
            ['body' => $payload],
        );
    }
}
