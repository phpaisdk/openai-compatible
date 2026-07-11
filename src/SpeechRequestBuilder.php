<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Requests\SpeechRequest;

final class SpeechRequestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $modelId, string $providerName, SpeechRequest $request): array
    {
        $body = [
            'model' => $modelId,
            'input' => $request->input,
            'voice' => $request->voice ?? 'alloy',
            'response_format' => $request->format ?? 'mp3',
        ];

        $providerOptions = $request->providerOptionsFor($providerName);
        $raw = $providerOptions['raw'] ?? null;
        unset($providerOptions['raw']);

        $body = array_replace($body, $providerOptions);

        if (is_array($raw)) {
            $body = array_replace($body, $raw);
        }

        return $body;
    }

    public static function expectedMimeType(string $format): string
    {
        return match ($format) {
            'wav' => 'audio/wav',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'pcm' => 'audio/pcm',
            default => 'audio/mpeg',
        };
    }
}
