<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\ContentSource;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\TranscriptionRequest;
use AiSdk\Utils\Support\MultipartFormData as CoreMultipartFormData;

final class TranscriptionRequestBuilder
{
    /**
     * @return array{body: string, boundary: string}
     */
    public static function build(
        string $modelId,
        string $providerName,
        TranscriptionRequest $request,
        bool $includeModel = true,
        bool $supportsUrl = false,
    ): array {
        $parts = [];

        if ($includeModel) {
            $parts[] = ['name' => 'model', 'value' => $modelId];
        }

        $providerOptions = $request->providerOptionsFor($providerName);
        $raw = $providerOptions['raw'] ?? null;
        unset($providerOptions['raw']);
        if (is_array($raw)) {
            $providerOptions = array_replace($providerOptions, $raw);
        }

        foreach ($providerOptions as $name => $value) {
            if ($value === null) {
                continue;
            }

            $field = $name === 'timestamp_granularities' ? 'timestamp_granularities[]' : (string) $name;
            $parts[] = ['name' => $field, 'value' => $value];
        }

        $audio = $request->audio;
        if ($audio->source() === ContentSource::Url) {
            if (! $supportsUrl) {
                throw new InvalidArgumentException("Provider [{$providerName}] transcription requires local, raw, base64, or data URI audio content.");
            }

            $parts[] = ['name' => 'url', 'value' => (string) $audio->url()];

            return CoreMultipartFormData::encode($parts);
        }

        $bytes = self::audioBytes($request);
        $mimeType = $audio->mimeType() ?? 'application/octet-stream';
        $filename = $audio->filename() ?? 'audio.' . self::format($mimeType);

        // Some providers, including xAI, require the file part to be last.
        $parts[] = [
            'name' => 'file',
            'value' => $bytes,
            'filename' => $filename,
            'contentType' => $mimeType,
        ];

        return CoreMultipartFormData::encode($parts);
    }

    public static function format(string $mimeType, ?string $filename = null): string
    {
        $extension = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return $extension;
        }

        return match (strtolower($mimeType)) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/opus' => 'opus',
            'audio/flac', 'audio/x-flac' => 'flac',
            'audio/webm' => 'webm',
            'audio/aac' => 'aac',
            default => 'wav',
        };
    }

    private static function audioBytes(TranscriptionRequest $request): string
    {
        $audio = $request->audio;
        if ($audio->source() === ContentSource::Raw && $audio->data() !== null) {
            return $audio->data();
        }

        $base64 = $audio->base64Data();
        $bytes = $base64 === null ? false : base64_decode($base64, true);
        if ($bytes === false) {
            throw new InvalidArgumentException('Transcription audio contains invalid base64 data.');
        }

        return $bytes;
    }
}
