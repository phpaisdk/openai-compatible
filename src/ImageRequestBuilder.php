<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Requests\ImageRequest;

/**
 * Builds an OpenAI-compatible /images/generations request body.
 */
final class ImageRequestBuilder
{
    /**
     * @param  array{
     *     aspectRatioParameter?: string|null,
     *     inferSizeFromAspectRatio?: bool,
     *     includeResponseFormat?: bool,
     *     sizeParameter?: string|null,
     *     seedParameter?: string|null
     * }  $options
     * @return array<string, mixed>
     */
    public static function build(string $modelId, string $providerName, ImageRequest $request, array $options = []): array
    {
        $body = [
            'model' => $modelId,
            'prompt' => $request->prompt,
            'n' => $request->count,
        ];

        if (($options['includeResponseFormat'] ?? true) === true) {
            $body['response_format'] = 'b64_json';
        }

        $sizeParameter = array_key_exists('sizeParameter', $options) ? $options['sizeParameter'] : 'size';
        if ($sizeParameter !== null) {
            $size = $request->size;
            if ($size === null && ($options['inferSizeFromAspectRatio'] ?? true) === true) {
                $size = self::sizeFromAspectRatio($request->aspectRatio);
            }

            if ($size !== null) {
                $body[$sizeParameter] = $size;
            }
        }

        $aspectRatioParameter = $options['aspectRatioParameter'] ?? null;
        if ($aspectRatioParameter !== null && $request->aspectRatio !== null) {
            $body[$aspectRatioParameter] = $request->aspectRatio;
        }

        $seedParameter = array_key_exists('seedParameter', $options) ? $options['seedParameter'] : 'seed';
        if ($seedParameter !== null && $request->seed !== null) {
            $body[$seedParameter] = $request->seed;
        }

        $raw = $request->providerOptionsFor($providerName)['raw'] ?? null;
        if (is_array($raw)) {
            $body = array_replace($body, $raw);
        }

        return $body;
    }

    private static function sizeFromAspectRatio(?string $aspectRatio): ?string
    {
        return match ($aspectRatio) {
            null => null,
            '1:1' => '1024x1024',
            '3:2', '16:9' => '1536x1024',
            '2:3', '9:16' => '1024x1536',
            default => throw new InvalidArgumentException(sprintf(
                'OpenAI-compatible image generation cannot infer a portable size for aspect ratio [%s]. Pass size() or providerOptions().',
                $aspectRatio,
            )),
        };
    }
}
