<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\FinishReason;
use AiSdk\InputEncoding;
use AiSdk\Message;
use AiSdk\OpenAICompatible\ResponsesRequestBuilder;
use AiSdk\OpenAICompatible\ResponsesRequestProfile;
use AiSdk\OpenAICompatible\ResponsesResponseParser;
use AiSdk\OpenAICompatible\ResponsesStreamParser;
use AiSdk\Reasoning;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Schema;
use AiSdk\Streaming\FinishPart;
use AiSdk\Streaming\StreamState;
use AiSdk\Streaming\TextDeltaPart;
use AiSdk\Tool;
use AiSdk\ToolCall;
use AiSdk\ToolChoice;

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

it('builds multimodal structured Responses requests with tools and provider options', function () {
    $request = new TextModelRequest(
        messages: [
            Message::user([
                Content::text('Extract the invoice total.'),
                Content::image('https://example.com/invoice.png'),
                Content::file('JVBERi0=', mimeType: 'application/pdf', filename: 'invoice.pdf', encoding: InputEncoding::Base64),
            ]),
            Message::assistant('', toolCalls: [new ToolCall('call_1', 'lookup_tax', ['country' => 'PK'])]),
            Message::tool(toolCallId: 'call_1', output: '{"rate":0.18}', name: 'lookup_tax'),
        ],
        output: \AiSdk\Outputs\Output::schema(Schema::object(
            name: 'invoice_total',
            properties: [Schema::number(name: 'total')->required()],
        )),
        tools: [Tool::make('lookup_tax', 'Look up a tax rate')],
        toolChoice: ToolChoice::tool('lookup_tax'),
        providerOptions: ['provider' => ['store' => false, 'prompt_cache_key' => 'invoice-extraction']],
    );

    $body = ResponsesRequestBuilder::build('model', 'provider', $request, false);

    expect($body['input'][0]['content'][0])->toBe(['type' => 'input_text', 'text' => 'Extract the invoice total.'])
        ->and($body['input'][0]['content'][1])->toBe(['type' => 'input_image', 'image_url' => 'https://example.com/invoice.png'])
        ->and($body['input'][0]['content'][2])->toBe([
            'type' => 'input_file',
            'filename' => 'invoice.pdf',
            'file_data' => 'data:application/pdf;base64,JVBERi0=',
        ])
        ->and($body['input'][1]['type'])->toBe('function_call')
        ->and($body['input'][2])->toBe(['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => '{"rate":0.18}'])
        ->and($body['tools'][0]['type'])->toBe('function')
        ->and($body['tool_choice'])->toBe(['type' => 'function', 'name' => 'lookup_tax'])
        ->and($body['text']['format']['type'])->toBe('json_schema')
        ->and($body['text']['format']['name'])->toBe('invoice_total')
        ->and($body['store'])->toBeFalse()
        ->and($body['prompt_cache_key'])->toBe('invoice-extraction');
});

it('allows providers to profile Responses wire differences explicitly', function () {
    $request = new TextModelRequest(
        messages: [Message::user('Hello')],
        reasoning: Reasoning::effort('high'),
    );

    $body = ResponsesRequestBuilder::build(
        'model',
        'provider',
        $request,
        false,
        new ResponsesRequestProfile(
            maxOutputTokensParameter: 'max_tokens',
            reasoningEffortParameter: 'reasoning_effort',
        ),
    );

    expect($body)->toHaveKey('max_tokens')
        ->and($body)->not->toHaveKey('max_output_tokens')
        ->and($body['reasoning_effort'])->toBe('high')
        ->and($body)->not->toHaveKey('temperature');
});

it('does not silently enable unsupported Responses features', function () {
    $request = new TextModelRequest(
        messages: [Message::user('Hello')],
        output: \AiSdk\Outputs\Output::schema(Schema::object(name: 'response')),
    );

    ResponsesRequestBuilder::build(
        'model',
        'provider',
        $request,
        false,
        new ResponsesRequestProfile(supportsStructuredOutput: false),
    );
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('normalizes terminal Responses function calls when no argument delta was sent', function () {
    $state = new StreamState();

    foreach (ResponsesStreamParser::parse([
        ['event' => 'response.output_item.done', 'data' => json_encode([
            'type' => 'response.output_item.done',
            'output_index' => 0,
            'item' => [
                'type' => 'function_call',
                'call_id' => 'call_1',
                'name' => 'weather',
                'arguments' => '{"city":"Oslo"}',
            ],
        ], JSON_THROW_ON_ERROR)],
        ['event' => 'response.reasoning_summary_text.delta', 'data' => json_encode([
            'type' => 'response.reasoning_summary_text.delta',
            'delta' => 'Checking weather.',
        ], JSON_THROW_ON_ERROR)],
        ['event' => 'response.completed', 'data' => json_encode([
            'type' => 'response.completed',
            'response' => ['status' => 'completed', 'output' => []],
        ], JSON_THROW_ON_ERROR)],
    ], 'provider') as $part) {
        $state->record($part);
    }

    expect($state->toolCalls())->toHaveCount(1)
        ->and($state->toolCalls()[0]->id)->toBe('call_1')
        ->and($state->toolCalls()[0]->name)->toBe('weather')
        ->and($state->toolCalls()[0]->arguments)->toBe(['city' => 'Oslo'])
        ->and($state->reasoning())->toBe('Checking weather.');
});

it('maps incomplete Responses status and rejects error envelopes', function () {
    $response = ResponsesResponseParser::parse([
        'status' => 'incomplete',
        'incomplete_details' => ['reason' => 'max_output_tokens'],
        'output' => [],
    ], 'provider');

    expect($response->finishReason)->toBe(FinishReason::Length);

    ResponsesResponseParser::parse([
        'error' => ['message' => 'Response endpoint unavailable.'],
    ], 'provider');
})->throws(\AiSdk\Exceptions\InvalidResponseException::class, 'Response endpoint unavailable.');
