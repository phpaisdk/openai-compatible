<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible\Support;

use AiSdk\Support\Usage;

final class ResponsesUsage
{
    /** @param array<string, mixed> $usage */
    public static function fromArray(array $usage): Usage
    {
        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $cached = isset($usage['input_tokens_details']['cached_tokens']) ? (int) $usage['input_tokens_details']['cached_tokens'] : null;
        $reasoning = isset($usage['output_tokens_details']['reasoning_tokens']) ? (int) $usage['output_tokens_details']['reasoning_tokens'] : null;

        return new Usage(
            inputTokens: $input,
            outputTokens: $output,
            totalTokens: isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null,
            reasoningTokens: $reasoning,
            cachedInputTokens: $cached,
            cacheWriteInputTokens: isset($usage['input_tokens_details']['cache_write_tokens']) ? (int) $usage['input_tokens_details']['cache_write_tokens'] : null,
        );
    }
}
