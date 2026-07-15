<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Utils\Support\MultipartFormData as CoreMultipartFormData;

/** @deprecated Use the provider-neutral core MultipartFormData utility. */
final class MultipartFormData
{
    /**
     * @param  array<int, array{name: string, value: mixed, filename?: string, contentType?: string}>  $parts
     * @return array{body: string, boundary: string}
     */
    public static function encode(array $parts): array
    {
        return CoreMultipartFormData::encode($parts);
    }
}
