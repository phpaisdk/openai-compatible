<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

/**
 * Describes the optional parts of an OpenAI-compatible Responses request.
 *
 * Providers select a profile for their endpoint quirks instead of duplicating
 * the shared request construction and response handling.
 */
final readonly class ResponsesRequestProfile
{
    public function __construct(
        public ?string $maxOutputTokensParameter = 'max_output_tokens',
        public bool $includeTemperature = true,
        public bool $omitSamplingWhenReasoning = true,
        public bool $supportsStructuredOutput = true,
        public ?string $reasoningEffortParameter = 'reasoning.effort',
    ) {}
}
