<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Responses\ImageResponse;
use AiSdk\Results\ImageData;
use AiSdk\Support\Usage;

final class ImageResponseParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function parse(array $payload, string $providerName): ImageResponse
    {
        $images = [];

        foreach (($payload['data'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $images[] = new ImageData(
                base64: is_string($item['b64_json'] ?? null) ? $item['b64_json'] : null,
                mimeType: self::mimeType($item, $payload),
                width: is_int($item['width'] ?? null) ? $item['width'] : null,
                height: is_int($item['height'] ?? null) ? $item['height'] : null,
                url: is_string($item['url'] ?? null) ? $item['url'] : null,
            );
        }

        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];

        if ($images === []) {
            throw InvalidResponseException::forProvider(
                $providerName,
                "Provider [{$providerName}] returned no generated images.",
                ['body' => $payload],
            );
        }

        return new ImageResponse(
            images: $images,
            usage: new Usage(
                inputTokens: self::intValue($usage, 'input_tokens') ?? self::intValue($usage, 'prompt_tokens') ?? 0,
                outputTokens: self::intValue($usage, 'output_tokens') ?? self::intValue($usage, 'completion_tokens') ?? 0,
                totalTokens: self::intValue($usage, 'total_tokens'),
            ),
            rawResponse: $payload,
            providerMetadata: [
                $providerName => array_filter([
                    'id' => is_string($payload['id'] ?? null) ? $payload['id'] : null,
                    'created' => is_int($payload['created'] ?? null) ? $payload['created'] : null,
                    'model' => is_string($payload['model'] ?? null) ? $payload['model'] : null,
                    'size' => is_string($payload['size'] ?? null) ? $payload['size'] : null,
                    'quality' => is_string($payload['quality'] ?? null) ? $payload['quality'] : null,
                    'output_format' => is_string($payload['output_format'] ?? null) ? $payload['output_format'] : null,
                ], static fn($value): bool => $value !== null),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function intValue(array $payload, string $key): ?int
    {
        return is_int($payload[$key] ?? null) ? $payload[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $payload
     */
    private static function mimeType(array $item, array $payload): string
    {
        if (is_string($item['mime_type'] ?? null)) {
            return $item['mime_type'];
        }
        if (is_string($item['media_type'] ?? null)) {
            return $item['media_type'];
        }

        return match ($payload['output_format'] ?? null) {
            'jpeg', 'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
