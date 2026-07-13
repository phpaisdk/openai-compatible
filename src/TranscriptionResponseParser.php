<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Responses\TranscriptionResponse;
use AiSdk\Results\TranscriptData;
use AiSdk\Results\TranscriptSegment;
use AiSdk\Support\Usage;
use Psr\Http\Message\ResponseInterface;

final class TranscriptionResponseParser
{
    /**
     * @param  ResponseInterface|array<string, mixed>  $response
     * @param  array<string, mixed>  $metadata
     */
    public static function parse(ResponseInterface|array $response, string $providerName, array $metadata = []): TranscriptionResponse
    {
        $payload = $response instanceof ResponseInterface
            ? self::payload($response)
            : $response;

        if (! array_key_exists('text', $payload) || ! is_string($payload['text'])) {
            throw InvalidResponseException::forProvider(
                $providerName,
                "Provider [{$providerName}] returned no transcription text.",
                ['body' => $payload],
            );
        }

        $usageData = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $inputTokens = self::intValue($usageData, 'input_tokens', 'prompt_tokens') ?? 0;
        $outputTokens = self::intValue($usageData, 'output_tokens', 'completion_tokens') ?? 0;
        $totalTokens = self::intValue($usageData, 'total_tokens');
        $duration = self::floatValue($payload, 'duration') ?? self::floatValue($usageData, 'seconds');

        return new TranscriptionResponse(
            transcript: new TranscriptData(
                text: $payload['text'],
                language: is_string($payload['language'] ?? null) ? $payload['language'] : null,
                duration: $duration,
                segments: self::segments($payload),
            ),
            usage: new Usage(
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                totalTokens: $totalTokens,
            ),
            rawResponse: $payload,
            providerMetadata: [
                $providerName => array_replace($metadata, array_filter([
                    'duration' => $duration,
                    'cost' => is_numeric($usageData['cost'] ?? null) ? (float) $usageData['cost'] : null,
                ], static fn(mixed $value): bool => $value !== null)),
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function payload(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['text' => $body];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, TranscriptSegment>
     */
    private static function segments(array $payload): array
    {
        $values = is_array($payload['segments'] ?? null)
            ? $payload['segments']
            : (is_array($payload['words'] ?? null) ? $payload['words'] : []);

        if ($values === [] && is_array($payload['channels'] ?? null)) {
            foreach ($payload['channels'] as $channel) {
                if (! is_array($channel) || ! is_array($channel['words'] ?? null)) {
                    continue;
                }

                foreach ($channel['words'] as $word) {
                    if (is_array($word)) {
                        $word['speaker'] ??= $channel['index'] ?? null;
                        $values[] = $word;
                    }
                }
            }
        }

        $segments = [];
        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $text = $value['text'] ?? $value['word'] ?? null;
            if (! is_string($text)) {
                continue;
            }

            $speaker = $value['speaker'] ?? null;
            $segments[] = new TranscriptSegment(
                text: $text,
                start: self::floatValue($value, 'start') ?? 0.0,
                end: self::floatValue($value, 'end') ?? 0.0,
                speaker: is_string($speaker) || is_int($speaker) ? (string) $speaker : null,
            );
        }

        return $segments;
    }

    /** @param array<string, mixed> $values */
    private static function intValue(array $values, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (is_numeric($values[$key] ?? null)) {
                return (int) $values[$key];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $values */
    private static function floatValue(array $values, string $key): ?float
    {
        return is_numeric($values[$key] ?? null) ? (float) $values[$key] : null;
    }
}
