<?php

declare(strict_types=1);

use AiSdk\FinishReason;
use AiSdk\Message;
use AiSdk\OpenAICompatible\ResponsesRequestBuilder;
use AiSdk\OpenAICompatible\ResponsesResponseParser;
use AiSdk\OpenAICompatible\ResponsesStreamParser;
use AiSdk\Reasoning;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\TextDeltaPart;

it('builds a Responses API request independently from Chat Completions', function () {
    $request = new TextModelRequest(
        messages: [Message::user('Hello')],
        system: 'Be concise.',
        maxTokens: 256,
        reasoning: Reasoning::effort('medium'),
        providerOptions: ['amazon-bedrock' => ['store' => false]],
    );

    $body = ResponsesRequestBuilder::build('openai.gpt-oss-120b', 'amazon-bedrock', $request, false);

    expect($body)->toMatchArray([
        'model' => 'openai.gpt-oss-120b',
        'instructions' => 'Be concise.',
        'max_output_tokens' => 256,
        'stream' => false,
        'reasoning' => ['effort' => 'medium'],
        'store' => false,
    ])->and($body['input'])->toBe([[
        'role' => 'user',
        'content' => [['type' => 'input_text', 'text' => 'Hello']],
    ]])->and($body)->not->toHaveKey('messages');
});

it('parses Responses output text tool calls reasoning and usage', function () {
    $response = ResponsesResponseParser::parse([
        'id' => 'resp_123',
        'object' => 'response',
        'status' => 'completed',
        'model' => 'openai.gpt-oss-120b',
        'output' => [
            ['type' => 'reasoning', 'summary' => [['type' => 'summary_text', 'text' => 'Checked.']]],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Hello']]],
            ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'weather', 'arguments' => '{"city":"Lahore"}'],
        ],
        'usage' => [
            'input_tokens' => 12,
            'output_tokens' => 8,
            'total_tokens' => 20,
            'input_tokens_details' => ['cached_tokens' => 4],
            'output_tokens_details' => ['reasoning_tokens' => 3],
        ],
    ], 'amazon-bedrock');

    expect($response->text())->toBe('Hello')
        ->and($response->reasoning())->toBe('Checked.')
        ->and($response->toolCalls()[0]->arguments)->toBe(['city' => 'Lahore'])
        ->and($response->finishReason)->toBe(FinishReason::ToolCalls)
        ->and($response->usage->inputTokens)->toBe(12)
        ->and($response->usage->reasoningTokens)->toBe(3);
});

it('parses Responses streaming events', function () {
    $parts = iterator_to_array(ResponsesStreamParser::parse([
        ['event' => 'response.created', 'data' => json_encode(['type' => 'response.created', 'response' => ['id' => 'resp_123', 'model' => 'model', 'status' => 'in_progress']], JSON_THROW_ON_ERROR)],
        ['event' => 'response.output_text.delta', 'data' => json_encode(['type' => 'response.output_text.delta', 'delta' => 'Hi'], JSON_THROW_ON_ERROR)],
        ['event' => 'response.completed', 'data' => json_encode([
            'type' => 'response.completed',
            'response' => [
                'status' => 'completed',
                'output' => [],
                'usage' => ['input_tokens' => 2, 'output_tokens' => 1, 'total_tokens' => 3],
            ],
        ], JSON_THROW_ON_ERROR)],
    ], 'amazon-bedrock'));

    $text = $parts[1] ?? null;
    $finish = $parts[2] ?? null;
    if (! $text instanceof TextDeltaPart || ! $finish instanceof FinishPart) {
        throw new LogicException('Expected normalized Responses stream parts.');
    }

    expect($parts)->toHaveCount(3)
        ->and($text)->toBeInstanceOf(TextDeltaPart::class)
        ->and($text->text)->toBe('Hi')
        ->and($finish)->toBeInstanceOf(FinishPart::class)
        ->and($finish->reason)->toBe(FinishReason::Stop)
        ->and($finish->usage->totalTokens)->toBe(3);
});
