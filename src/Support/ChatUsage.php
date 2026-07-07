<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible\Support;

use AiSdk\Support\Usage;

final class ChatUsage
{
    /**
     * @param  array<string, mixed>  $usage
     */
    public static function fromArray(array $usage): Usage
    {
        $details = self::arrayValue($usage, 'completion_tokens_details')
            ?? self::arrayValue($usage, 'output_tokens_details')
            ?? [];
        $promptDetails = self::arrayValue($usage, 'prompt_tokens_details')
            ?? self::arrayValue($usage, 'input_tokens_details')
            ?? [];

        return new Usage(
            inputTokens: self::intValue($usage, 'prompt_tokens', 'input_tokens', 'promptTokens', 'inputTokens', 'promptTokenCount', 'prompt_token_count') ?? 0,
            outputTokens: self::intValue($usage, 'completion_tokens', 'output_tokens', 'completionTokens', 'outputTokens', 'candidatesTokenCount', 'candidates_token_count') ?? 0,
            totalTokens: self::intValue($usage, 'total_tokens', 'totalTokens', 'totalTokenCount', 'total_token_count'),
            reasoningTokens: self::intValue($details, 'reasoning_tokens', 'reasoningTokens', 'thoughtsTokenCount', 'thoughts_token_count'),
            cachedInputTokens: self::intValue($promptDetails, 'cached_tokens', 'cachedTokens', 'cached_input_tokens', 'cachedInputTokens'),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  non-empty-string  ...$keys
     */
    private static function intValue(array $values, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_numeric($values[$key])) {
                return (int) $values[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private static function arrayValue(array $values, string $key): ?array
    {
        return isset($values[$key]) && is_array($values[$key]) ? $values[$key] : null;
    }
}
