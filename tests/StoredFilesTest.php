<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\Files\FileReference;
use AiSdk\Message;
use AiSdk\OpenAICompatible\ChatRequestBuilder;
use AiSdk\OpenAICompatible\ResponsesRequestBuilder;
use AiSdk\Requests\TextModelRequest;

it('uses native file_id references and file_url fields', function () {
    $request = new TextModelRequest([Message::user([
        Content::storedFile(new FileReference('openai', 'file-a')),
        Content::storedFile(new FileReference('openai', 'file-image'), image: true),
        Content::file('https://example.test/report.pdf'),
    ])]);
    $body = ResponsesRequestBuilder::build('m', 'openai', $request, false);
    expect($body['input'][0]['content'])->toBe([
        ['type' => 'input_file', 'file_id' => 'file-a'],
        ['type' => 'input_image', 'file_id' => 'file-image'],
        ['type' => 'input_file', 'file_url' => 'https://example.test/report.pdf'],
    ]);
    expect(fn() => ResponsesRequestBuilder::build('m', 'xai', $request, false))->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class);
});

it('supports document ids in chat but rejects stored images', function () {
    $request = new TextModelRequest([Message::user([Content::storedFile(new FileReference('openai', 'f'))])]);
    expect(ChatRequestBuilder::build('m', 'openai', $request, false)['messages'][0]['content'][0])->toBe(['type' => 'file', 'file' => ['file_id' => 'f']]);
    $image = new TextModelRequest([Message::user([Content::storedFile(new FileReference('openai', 'f'), image: true)])]);
    expect(fn() => ChatRequestBuilder::build('m', 'openai', $image, false))->toThrow(\AiSdk\Exceptions\InvalidArgumentException::class);
});
