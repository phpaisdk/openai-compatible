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
        $cached = (int) ($usage['input_tokens_details']['cached_tokens'] ?? 0);
        $reasoning = (int) ($usage['output_tokens_details']['reasoning_tokens'] ?? 0);

        return new Usage(
            inputTokens: $input,
            outputTokens: $output,
            totalTokens: isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null,
            reasoningTokens: $reasoning > 0 ? $reasoning : null,
            cachedInputTokens: $cached > 0 ? $cached : null,
        );
    }
}
