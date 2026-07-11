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
    ): array {
        $body = [
            'model' => $modelId,
            'input' => ResponsesInputConverter::convert($request->messages),
            'max_output_tokens' => $request->maxTokens,
            'stream' => $stream,
        ];

        if ($request->system !== null && $request->system !== '') {
            $body['instructions'] = $request->system;
        }

        if ($request->reasoning === null) {
            $body['temperature'] = $request->temperature;

            if ($request->topP !== null) {
                $body['top_p'] = $request->topP;
            }
        }

        if ($request->reasoning?->budgetTokens !== null) {
            throw new InvalidArgumentException('OpenAI-compatible Responses reasoning does not accept portable token budgets. Use Reasoning::effort(...) or provider options.');
        }

        if ($request->reasoning?->effort !== null) {
            $body['reasoning'] = ['effort' => $request->reasoning->effort];
        }

        if ($request->tools !== []) {
            $body['tools'] = array_map(self::tool(...), $request->tools);
            $body['tool_choice'] = self::toolChoice($request->toolChoice);
        }

        if ($request->output instanceof Output) {
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
