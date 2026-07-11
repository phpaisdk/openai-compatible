<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Requests\EmbeddingRequest;

final class EmbeddingRequestBuilder
{
    /**
     * @param  array{
     *     dimensionsParameter?: string|null,
     *     includeEncodingFormat?: bool,
     * }  $options
     * @return array<string, mixed>
     */
    public static function build(string $modelId, string $providerName, EmbeddingRequest $request, array $options = []): array
    {
        $body = [
            'model' => $modelId,
            'input' => $request->inputs,
        ];

        if (($options['includeEncodingFormat'] ?? true) === true) {
            $body['encoding_format'] = 'float';
        }

        $dimensionsParameter = array_key_exists('dimensionsParameter', $options)
            ? $options['dimensionsParameter']
            : 'dimensions';

        if ($dimensionsParameter !== null && $request->dimensions !== null) {
            $body[$dimensionsParameter] = $request->dimensions;
        }

        $providerOptions = $request->providerOptionsFor($providerName);
        $raw = $providerOptions['raw'] ?? null;
        unset($providerOptions['raw']);

        $body = array_replace($body, $providerOptions);

        return is_array($raw) ? array_replace($body, $raw) : $body;
    }
}
