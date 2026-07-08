<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Responses\SpeechResponse;
use AiSdk\Results\AudioData;
use AiSdk\Support\Usage;
use Psr\Http\Message\ResponseInterface;

final class SpeechResponseParser
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function parse(ResponseInterface $response, string $providerName, string $fallbackMimeType, array $metadata = []): SpeechResponse
    {
        $headers = self::metadata($response);

        return new SpeechResponse(
            audio: new AudioData(
                data: (string) $response->getBody(),
                mimeType: $response->getHeaderLine('Content-Type') ?: $fallbackMimeType,
            ),
            usage: Usage::empty(),
            rawResponse: [],
            providerMetadata: [$providerName => array_replace($metadata, $headers)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadata(ResponseInterface $response): array
    {
        $metadata = [];

        foreach (['x-generation-id', 'x-request-id', 'request-id'] as $header) {
            $value = $response->getHeaderLine($header);
            if ($value !== '') {
                $metadata[$header] = $value;
            }
        }

        return $metadata;
    }
}
