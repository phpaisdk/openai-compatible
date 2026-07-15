<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\OpenAICompatible\Converters\ResponsesInputConverter;
use AiSdk\Outputs\Output;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Tool;
use AiSdk\ToolChoice;

final class ResponsesRequestBuilder
{
    /** @return array<string, mixed> */
    public static function build(
        string $modelId,
        string $providerName,
        TextModelRequest $request,
        bool $stream,
        ?ResponsesRequestProfile $profile = null,
    ): array {
        $profile ??= new ResponsesRequestProfile();

        $body = [
            'model' => $modelId,
            'input' => ResponsesInputConverter::convert($request->messages),
            'stream' => $stream,
        ];

        if ($profile->maxOutputTokensParameter !== null) {
            $body[$profile->maxOutputTokensParameter] = $request->maxTokens;
        }

        if ($request->system !== null && $request->system !== '') {
            $body['instructions'] = $request->system;
        }

        if ($profile->includeTemperature && ! ($profile->omitSamplingWhenReasoning && $request->reasoning !== null)) {
            $body['temperature'] = $request->temperature;

            if ($request->topP !== null) {
                $body['top_p'] = $request->topP;
            }
        }

        if ($request->reasoning?->budgetTokens !== null) {
            throw new InvalidArgumentException('OpenAI-compatible Responses reasoning does not accept portable token budgets. Use Reasoning::effort(...) or provider options.');
        }

        if ($request->reasoning?->effort !== null) {
            if ($profile->reasoningEffortParameter === null) {
                throw new InvalidArgumentException("Provider [{$providerName}] does not support portable reasoning effort through its Responses adapter.");
            }

            self::setPath($body, $profile->reasoningEffortParameter, $request->reasoning->effort);
        }

        if ($request->tools !== []) {
            $body['tools'] = array_map(self::tool(...), $request->tools);
            $body['tool_choice'] = self::toolChoice($request->toolChoice);
        }

        if ($request->output instanceof Output) {
            if (! $profile->supportsStructuredOutput) {
                throw new InvalidArgumentException("Provider [{$providerName}] does not support structured output through its Responses adapter.");
            }

            $body['text'] = ['format' => self::outputFormat($request->output)];
        }

        $providerOptions = $request->providerOptionsFor($providerName);
        $raw = $providerOptions['raw'] ?? null;
        unset($providerOptions['raw']);

        $body = array_replace($body, $providerOptions);

        if (is_array($raw)) {
            $body = array_replace($body, $raw);
        }

        return $body;
    }

    /** @param array<string, mixed> $body */
    private static function setPath(array &$body, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $target = & $body;

        foreach ($segments as $segment) {
            if (! isset($target[$segment]) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = & $target[$segment];
        }

        $target = $value;
    }

    /** @return array<string, mixed> */
    private static function tool(Tool $tool): array
    {
        return [
            'type' => 'function',
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->inputSchemaForProvider(),
            'strict' => true,
        ];
    }

    /** @return string|array<string, string> */
    private static function toolChoice(?ToolChoice $choice): string|array
    {
        if ($choice === null) {
            return 'auto';
        }

        return $choice->type === ToolChoice::TOOL
            ? ['type' => 'function', 'name' => (string) $choice->toolName]
            : $choice->type;
    }

    /** @return array<string, mixed> */
    private static function outputFormat(Output $output): array
    {
        if ($output->kind === Output::KIND_OBJECT && $output->schema instanceof \AiSdk\Schema) {
            return [
                'type' => 'json_schema',
                'name' => $output->schema->name() ?? 'response',
                'strict' => true,
                'schema' => $output->schema->jsonSchema(),
            ];
        }

        return $output->kind === Output::KIND_OBJECT
            ? ['type' => 'json_object']
            : ['type' => 'text'];
    }
}
