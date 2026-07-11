<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

/**
 * Describes the optional parts of an OpenAI-compatible Chat Completions wire
 * format. Provider packages own these choices; compatibility is never assumed
 * merely because an endpoint uses the same path and envelope.
 */
final readonly class ChatRequestProfile
{
    public function __construct(
        public ?string $maxTokensParameter = 'max_tokens',
        public bool $includeTemperature = true,
        public bool $omitTemperatureWhenReasoning = false,
        public bool $includeStreamOptions = true,
        public bool $supportsStructuredOutput = true,
        public ?string $reasoningEffortParameter = 'reasoning_effort',
    ) {}

    public static function openAI(string $modelId): self
    {
        return new self(
            maxTokensParameter: 'max_completion_tokens',
            includeTemperature: ! self::isReasoningModel($modelId),
            omitTemperatureWhenReasoning: true,
        );
    }

    public static function azure(): self
    {
        return new self(
            maxTokensParameter: 'max_completion_tokens',
            omitTemperatureWhenReasoning: true,
        );
    }

    private static function isReasoningModel(string $modelId): bool
    {
        return preg_match('/^(?:o\d|gpt-5)/i', $modelId) === 1;
    }
}
