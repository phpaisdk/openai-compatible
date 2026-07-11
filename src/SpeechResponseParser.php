<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
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
        $data = (string) $response->getBody();
        if ($data === '') {
            throw InvalidResponseException::forProvider($providerName, "Provider [{$providerName}] returned an empty speech response.");
        }

        return new SpeechResponse(
            audio: new AudioData(
                data: $data,
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
