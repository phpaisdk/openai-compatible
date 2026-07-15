<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\InputEncoding;
use AiSdk\Message;
use AiSdk\OpenAICompatible\ChatRequestBuilder;
use AiSdk\OpenAICompatible\ChatRequestProfile;
use AiSdk\OpenAICompatible\ChatResponseParser;
use AiSdk\OpenAICompatible\ChatStreamParser;
use AiSdk\OpenAICompatible\ImageRequestBuilder;
use AiSdk\OpenAICompatible\ImageResponseParser;
use AiSdk\Reasoning;
use AiSdk\Requests\ImageRequest;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Schema;
use AiSdk\Streaming\StreamState;

/** @param array<string, mixed> $overrides */
function request(array $overrides = []): TextModelRequest
{
    return new TextModelRequest(
        messages: $overrides['messages'] ?? [Message::user('Hi')],
        system: $overrides['system'] ?? null,
        reasoning: $overrides['reasoning'] ?? null,
        providerOptions: $overrides['providerOptions'] ?? [],
    );
}

it('builds a basic chat body', function () {
    $body = ChatRequestBuilder::build('gpt-4o', 'openai', request(['system' => 'Be terse']), false);

    expect($body['model'])->toBe('gpt-4o')
        ->and($body['messages'][0])->toBe(['role' => 'system', 'content' => 'Be terse'])
        ->and($body['messages'][1])->toBe(['role' => 'user', 'content' => 'Hi'])
        ->and($body['stream'])->toBeFalse();
});

it('maps reasoning effort through a single path', function () {
    $body = ChatRequestBuilder::build('o3', 'openai', request(['reasoning' => Reasoning::effort('high')]), false);

    expect($body['reasoning_effort'])->toBe('high');
});

it('uses the OpenAI request profile for modern token limits', function () {
    $body = ChatRequestBuilder::build(
        'gpt-4o',
        'openai',
        request(),
        false,
        ChatRequestProfile::openAI('gpt-4o'),
    );

    expect($body)->toHaveKey('max_completion_tokens')
        ->and($body)->not->toHaveKey('max_tokens');
});

it('omits unsupported temperature for OpenAI reasoning models', function () {
    $body = ChatRequestBuilder::build(
        'o3',
        'openai',
        request(['reasoning' => Reasoning::effort('high')]),
        false,
        ChatRequestProfile::openAI('o3'),
    );

    expect($body)->not->toHaveKey('temperature')
        ->and($body['reasoning_effort'])->toBe('high');
});

it('does not silently ignore explicit reasoning token budgets', function () {
    ChatRequestBuilder::build('o3', 'openai', request(['reasoning' => Reasoning::budget(4096)]), false);
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('merges the provider raw escape hatch last', function () {
    $body = ChatRequestBuilder::build('gpt-4o', 'openai', request([
        'providerOptions' => ['openai' => ['raw' => ['temperature' => 0.1, 'seed' => 42]]],
    ]), false);

    expect($body['temperature'])->toBe(0.1)
        ->and($body['seed'])->toBe(42);
});

it('builds native JSON Schema output and an explicit tool choice', function () {
    $body = ChatRequestBuilder::build('gpt-4o', 'openai', new TextModelRequest(
        messages: [Message::user('Extract an address.')],
        output: \AiSdk\Outputs\Output::schema(Schema::object(
            name: 'address',
            properties: [Schema::string(name: 'city')->required()],
        )),
        tools: [\AiSdk\Tool::make('lookup_postcode', 'Look up a postcode')],
        toolChoice: \AiSdk\ToolChoice::tool('lookup_postcode'),
    ), false);

    expect($body['response_format']['type'])->toBe('json_schema')
        ->and($body['response_format']['json_schema']['name'])->toBe('address')
        ->and($body['response_format']['json_schema']['strict'])->toBeTrue()
        ->and($body['tool_choice'])->toBe([
            'type' => 'function',
            'function' => ['name' => 'lookup_postcode'],
        ]);
});

it('parses a chat response with a tool call', function () {
    $payload = [
        'id' => 'chatcmpl_123',
        'object' => 'chat.completion',
        'created' => 1710000000,
        'model' => 'gpt-4o',
        'system_fingerprint' => 'fp_abc',
        'choices' => [[
            'index' => 0,
            'message' => [
                'content' => 'hello',
                'tool_calls' => [[
                    'id' => 'call_1',
                    'function' => ['name' => 'weather', 'arguments' => '{"city":"Lahore"}'],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
    ];

    $response = ChatResponseParser::parse($payload, 'openai');

    expect($response->text())->toBe('hello')
        ->and($response->toolCalls())->toHaveCount(1)
        ->and($response->toolCalls()[0]->arguments)->toBe(['city' => 'Lahore'])
        ->and($response->usage->inputTokens)->toBe(10)
        ->and($response->providerMetadata['openai']['id'])->toBe('chatcmpl_123')
        ->and($response->providerMetadata['openai']['model'])->toBe('gpt-4o')
        ->and($response->providerMetadata['openai']['choice_finish_reason'])->toBe('tool_calls');
});

it('parses provider-neutral usage token fields', function () {
    $response = ChatResponseParser::parse([
        'choices' => [[
            'message' => ['content' => 'hello'],
            'finish_reason' => 'stop',
        ]],
        'usage' => [
            'input_tokens' => 12,
            'output_tokens' => 8,
            'total_tokens' => 20,
            'output_tokens_details' => ['reasoning_tokens' => 3],
            'input_tokens_details' => ['cached_tokens' => 4],
        ],
    ], 'openai-compatible');

    expect($response->usage->inputTokens)->toBe(12)
        ->and($response->usage->outputTokens)->toBe(8)
        ->and($response->usage->totalTokens)->toBe(20)
        ->and($response->usage->reasoningTokens)->toBe(3)
        ->and($response->usage->cachedInputTokens)->toBe(4);
});

it('preserves reasoning returned by a synchronous response', function () {
    $response = ChatResponseParser::parse([
        'choices' => [[
            'message' => [
                'content' => '42',
                'reasoning_content' => 'I checked the arithmetic.',
            ],
            'finish_reason' => 'stop',
        ]],
    ], 'openai-compatible');

    expect($response->text())->toBe('42')
        ->and($response->reasoning())->toBe('I checked the arithmetic.');
});

it('rejects provider error envelopes returned with a success status', function () {
    ChatResponseParser::parse([
        'error' => ['message' => 'Model is unavailable.'],
    ], 'openai-compatible');
})->throws(\AiSdk\Exceptions\InvalidResponseException::class, 'Model is unavailable.');

it('rejects malformed stream events instead of silently dropping them', function () {
    iterator_to_array(ChatStreamParser::parse([
        ['event' => null, 'data' => '{not-json'],
    ], 'openai-compatible'));
})->throws(\AiSdk\Exceptions\InvalidResponseException::class);

it('parses streamed tool-call fragments into one accumulated call', function () {
    $events = [
        ['event' => null, 'data' => json_encode(['id' => 'chatcmpl_stream', 'model' => 'gpt-4o', 'choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_1', 'function' => ['name' => 'weather', 'arguments' => '']]]]]]], JSON_THROW_ON_ERROR)],
        ['event' => null, 'data' => json_encode(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"ci']]]]]]], JSON_THROW_ON_ERROR)],
        ['event' => null, 'data' => json_encode(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => 'ty":"Oslo"}']]]]]]], JSON_THROW_ON_ERROR)],
        ['event' => null, 'data' => json_encode(['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]], JSON_THROW_ON_ERROR)],
        ['event' => null, 'data' => '[DONE]'],
    ];

    $state = new StreamState();
    foreach (ChatStreamParser::parse($events, 'openai') as $part) {
        $state->record($part);
    }

    expect($state->toolCalls())->toHaveCount(1)
        ->and($state->toolCalls()[0]->name)->toBe('weather')
        ->and($state->toolCalls()[0]->arguments)->toBe(['city' => 'Oslo'])
        ->and($state->providerMetadata()['openai']['id'])->toBe('chatcmpl_stream')
        ->and($state->providerMetadata()['openai']['choice_finish_reason'])->toBe('tool_calls');
});

it('includes tool result names in converted messages', function () {
    $body = ChatRequestBuilder::build('gpt-4o', 'openai', request([
        'messages' => [
            Message::user('Use the weather tool.'),
            Message::tool(toolCallId: 'call_1', output: 'Sunny', name: 'weather'),
        ],
    ]), false);

    expect($body['messages'][1])->toMatchArray([
        'role' => 'tool',
        'tool_call_id' => 'call_1',
        'name' => 'weather',
        'content' => 'Sunny',
    ]);
});

it('includes assistant tool calls in converted messages', function () {
    $body = ChatRequestBuilder::build('gpt-4o', 'openai', request([
        'messages' => [
            Message::assistant('', toolCalls: [
                new \AiSdk\ToolCall('call_1', 'weather', ['city' => 'Lahore']),
            ]),
        ],
    ]), false);

    expect($body['messages'][0])->toMatchArray([
        'role' => 'assistant',
        'content' => null,
        'tool_calls' => [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => [
                'name' => 'weather',
                'arguments' => '{"city":"Lahore"}',
            ],
        ]],
    ]);
});

it('converts typed multimodal input content', function () {
    $body = ChatRequestBuilder::build('gpt-4o-audio-preview', 'openai', request([
        'messages' => [
            Message::user([
                Content::text('Describe these inputs.'),
                Content::image('https://example.com/photo.png'),
                Content::audio('UklGRg==', mimeType: 'audio/wav', encoding: InputEncoding::Base64),
                Content::file('JVBERi0=', mimeType: 'application/pdf', filename: 'report.pdf', encoding: InputEncoding::Base64),
            ]),
        ],
    ]), false);

    expect($body['messages'][0]['content'][0])->toBe(['type' => 'text', 'text' => 'Describe these inputs.'])
        ->and($body['messages'][0]['content'][1])->toBe(['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/photo.png']])
        ->and($body['messages'][0]['content'][2])->toBe(['type' => 'input_audio', 'input_audio' => ['data' => 'UklGRg==', 'format' => 'wav']])
        ->and($body['messages'][0]['content'][3])->toBe([
            'type' => 'file',
            'file' => [
                'filename' => 'report.pdf',
                'file_data' => 'data:application/pdf;base64,JVBERi0=',
            ],
        ]);
});

it('builds an image generation body', function () {
    $body = ImageRequestBuilder::build('image-model', 'openai-compatible', new ImageRequest(
        prompt: 'A clean product photo',
        count: 2,
        size: '1024x1024',
        providerOptions: ['openai-compatible' => ['raw' => ['background' => 'transparent']]],
    ));

    expect($body)->toMatchArray([
        'model' => 'image-model',
        'prompt' => 'A clean product photo',
        'n' => 2,
        'size' => '1024x1024',
        'response_format' => 'b64_json',
        'background' => 'transparent',
    ]);
});

it('maps common image aspect ratios to OpenAI-compatible sizes', function () {
    $body = ImageRequestBuilder::build('image-model', 'openai-compatible', new ImageRequest(
        prompt: 'A wide product scene',
        aspectRatio: '16:9',
    ));

    expect($body['size'])->toBe('1536x1024');
});

it('rejects image aspect ratios that cannot be mapped to an OpenAI-compatible size', function () {
    ImageRequestBuilder::build('image-model', 'openai-compatible', new ImageRequest(
        prompt: 'A cinematic scene',
        aspectRatio: '21:9',
    ));
})->throws(\AiSdk\Exceptions\InvalidArgumentException::class);

it('parses an image generation response', function () {
    $response = ImageResponseParser::parse([
        'id' => 'img_123',
        'created' => 1710000000,
        'model' => 'image-model',
        'size' => '1024x1024',
        'data' => [[
            'b64_json' => base64_encode('image-bytes'),
            'mime_type' => 'image/webp',
            'width' => 1024,
            'height' => 1024,
        ]],
        'usage' => [
            'input_tokens' => 12,
            'output_tokens' => 8,
            'total_tokens' => 20,
        ],
    ], 'openai-compatible');

    expect($response->first()?->bytes())->toBe('image-bytes')
        ->and($response->first()?->mimeType)->toBe('image/webp')
        ->and($response->usage->totalTokens)->toBe(20)
        ->and($response->providerMetadata['openai-compatible']['id'])->toBe('img_123');
});

it('uses the top-level output format as the generated image mime type', function () {
    $response = ImageResponseParser::parse([
        'output_format' => 'webp',
        'data' => [['b64_json' => base64_encode('image-bytes')]],
    ], 'openai-compatible');

    expect($response->first()?->mimeType)->toBe('image/webp');
});

it('rejects image responses without generated image data', function () {
    ImageResponseParser::parse(['data' => []], 'openai-compatible');
})->throws(\AiSdk\Exceptions\InvalidResponseException::class);
