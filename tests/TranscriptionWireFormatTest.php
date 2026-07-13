<?php

declare(strict_types=1);

use AiSdk\Content;
use AiSdk\OpenAICompatible\TranscriptionRequestBuilder;
use AiSdk\OpenAICompatible\TranscriptionResponseParser;
use AiSdk\Requests\TranscriptionRequest;
use Nyholm\Psr7\Response;

it('builds an OpenAI-compatible multipart transcription request', function () {
    $multipart = TranscriptionRequestBuilder::build(
        'whisper-1',
        'openai',
        new TranscriptionRequest(
            audio: Content::audio('RIFF-audio', mimeType: 'audio/wav', filename: 'sample.wav'),
            providerOptions: ['openai' => [
                'language' => 'en',
                'timestamp_granularities' => ['word', 'segment'],
            ]],
        ),
    );

    expect($multipart['boundary'])->toStartWith('aisdk-')
        ->and($multipart['body'])->toContain('name="model"', 'whisper-1')
        ->and($multipart['body'])->toContain('name="language"', 'en')
        ->and(substr_count($multipart['body'], 'name="timestamp_granularities[]"'))->toBe(2)
        ->and($multipart['body'])->toContain('name="file"; filename="sample.wav"', 'RIFF-audio');
});

it('keeps the file as the final multipart field', function () {
    $multipart = TranscriptionRequestBuilder::build(
        'grok-transcribe',
        'xai',
        new TranscriptionRequest(
            audio: Content::audio('audio', mimeType: 'audio/mpeg', filename: 'sample.mp3'),
            providerOptions: ['xai' => ['language' => 'en', 'format' => true]],
        ),
        includeModel: false,
    );

    $filePosition = strrpos($multipart['body'], 'name="file"');
    $formatPosition = strrpos($multipart['body'], 'name="format"');
    if ($filePosition === false || $formatPosition === false) {
        throw new RuntimeException('Expected multipart fields were not found.');
    }

    expect($filePosition)->toBeGreaterThan($formatPosition);
});

it('parses transcription text, word timestamps, duration, and usage', function () {
    $response = new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'text' => 'Hello world',
        'language' => 'en',
        'duration' => 1.5,
        'words' => [
            ['word' => 'Hello', 'start' => 0.0, 'end' => 0.5],
            ['word' => 'world', 'start' => 0.6, 'end' => 1.2],
        ],
        'usage' => ['input_tokens' => 4, 'output_tokens' => 2, 'total_tokens' => 6],
    ], JSON_THROW_ON_ERROR));

    $result = TranscriptionResponseParser::parse($response, 'openai', ['model' => 'whisper-1']);

    expect($result->transcript->text)->toBe('Hello world')
        ->and($result->transcript->language)->toBe('en')
        ->and($result->transcript->duration)->toBe(1.5)
        ->and($result->transcript->segments)->toHaveCount(2)
        ->and($result->transcript->segments[0]->text)->toBe('Hello')
        ->and($result->usage->totalTokens)->toBe(6)
        ->and($result->providerMetadata['openai']['model'])->toBe('whisper-1');
});

it('accepts plain text transcription responses', function () {
    $response = new Response(200, ['Content-Type' => 'text/plain'], 'Plain transcript');

    expect(TranscriptionResponseParser::parse($response, 'groq')->transcript->text)->toBe('Plain transcript');
});
