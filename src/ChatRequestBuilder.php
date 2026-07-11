<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\OpenAICompatible\Converters\ChatMessageConverter;
use AiSdk\OpenAICompatible\Converters\ChatToolConverter;
use AiSdk\Outputs\Output;
use AiSdk\Requests\TextModelRequest;

/**
 * Builds an OpenAI-compatible /chat/completions request body. Reusable by any
 * OpenAI-compatible provider. Reasoning resolves through ONE path: normalized
 * effort -> reasoning_effort, with a single provider `raw` escape hatch.
 */
final class ChatRequestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(
        string $modelId,
        string $providerName,
        TextModelRequest $request,
        bool $stream,
        ?ChatRequestProfile $profile = null,
    ): array {
        $profile ??= new ChatRequestProfile();

        $body = [
            'model' => $modelId,
            'messages' => ChatMessageConverter::convert($request->messages, $request->system),
            'stream' => $stream,
        ];

        if ($profile->includeTemperature && ! ($profile->omitTemperatureWhenReasoning && $request->reasoning !== null)) {
            $body['temperature'] = $request->temperature;
        }

        if ($profile->maxTokensParameter !== null) {
            $body[$profile->maxTokensParameter] = $request->maxTokens;
        }

        if ($request->topP !== null) {
            $body['top_p'] = $request->topP;
        }

        if ($request->tools !== []) {
            $body['tools'] = ChatToolConverter::convert($request->tools);
            $body['tool_choice'] = ChatToolConverter::choice($request->toolChoice);
        }

        if ($request->output instanceof Output) {
            if (! $profile->supportsStructuredOutput) {
                throw new InvalidArgumentException("Provider [{$providerName}] does not support structured output through its Chat Completions adapter.");
            }

            $body = array_replace($body, self::responseFormat($request->output));
        }

        if ($request->reasoning?->budgetTokens !== null) {
            throw new InvalidArgumentException('OpenAI-compatible reasoning does not accept portable token budgets. Use Reasoning::effort(...) or provider raw options.');
        }

        if ($request->reasoning?->effort !== null) {
            if ($profile->reasoningEffortParameter === null) {
                throw new InvalidArgumentException("Provider [{$providerName}] does not support portable reasoning effort through its Chat Completions adapter.");
            }

            $body[$profile->reasoningEffortParameter] = $request->reasoning->effort;
        }

        if ($stream && $profile->includeStreamOptions) {
            $body['stream_options'] = ['include_usage' => true];
        }

        $providerOptions = $request->providerOptionsFor($providerName);
        $raw = $providerOptions['raw'] ?? null;
        unset($providerOptions['raw']);

        $body = array_replace($body, $providerOptions);

        // Keep the legacy `raw` nesting as a compatibility alias. Direct,
        // provider-namespaced options are the primary PHP API.
        if (is_array($raw)) {
            $body = array_replace($body, $raw);
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private static function responseFormat(Output $output): array
    {
        if ($output->kind === Output::KIND_OBJECT && $output->schema instanceof \AiSdk\Schema) {
            return [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $output->schema->name() ?? 'response',
                        'strict' => true,
                        'schema' => $output->schema->jsonSchema(),
                    ],
                ],
            ];
        }

        if ($output->kind === Output::KIND_OBJECT) {
            return ['response_format' => ['type' => 'json_object']];
        }

        return [];
    }
}
