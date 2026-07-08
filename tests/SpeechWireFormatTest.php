<?php

declare(strict_types=1);

use AiSdk\OpenAICompatible\SpeechRequestBuilder;
use AiSdk\OpenAICompatible\SpeechResponseParser;
use AiSdk\Requests\SpeechRequest;
use Nyholm\Psr7\Response;

it('builds OpenAI-compatible speech request bodies', function () {
    $body = SpeechRequestBuilder::build('openai/gpt-4o-mini-tts', 'openrouter', new SpeechRequest(
        input: 'Say hello.',
        voice: 'coral',
        format: 'pcm',
        providerOptions: ['openrouter' => ['raw' => ['speed' => 1.2]]],
    ));

    expect($body)->toMatchArray([
        'model' => 'openai/gpt-4o-mini-tts',
        'input' => 'Say hello.',
        'voice' => 'coral',
        'response_format' => 'pcm',
        'speed' => 1.2,
    ]);
});

it('parses OpenAI-compatible raw speech responses', function () {
    $response = new Response(200, ['Content-Type' => 'audio/pcm', 'X-Generation-Id' => 'gen_123'], 'audio-bytes');

    $parsed = SpeechResponseParser::parse($response, 'openrouter', 'audio/mpeg', ['model' => 'microsoft/mai-voice-2']);

    expect($parsed->audio->data)->toBe('audio-bytes')
        ->and($parsed->audio->mimeType)->toBe('audio/pcm')
        ->and($parsed->providerMetadata['openrouter']['model'])->toBe('microsoft/mai-voice-2')
        ->and($parsed->providerMetadata['openrouter']['x-generation-id'])->toBe('gen_123');
});
