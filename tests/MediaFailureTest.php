<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\OpenAICompatible\ImageResponseParser;
use AiSdk\OpenAICompatible\SpeechResponseParser;
use AiSdk\OpenAICompatible\TranscriptionResponseParser;
use Nyholm\Psr7\Response;

it('rejects image answers whose data is not a list without PHP warnings', function () {
    set_error_handler(static fn(int $no, string $str) => throw new ErrorException($str));

    try {
        expect(fn() => ImageResponseParser::parse(['data' => 'oops'], 'p'))->toThrow(InvalidResponseException::class);
    } finally {
        restore_error_handler();
    }
});

it('treats a JSON or HTML body from an audio endpoint as an error, not as speech', function () {
    $json = new Response(200, ['Content-Type' => 'application/json'], json_encode(['error' => ['type' => 'rate_limit_error', 'message' => 'slow']]));
    expect(fn() => SpeechResponseParser::parse($json, 'p', 'audio/mpeg'))->toThrow(RateLimitException::class);

    $html = new Response(200, ['Content-Type' => 'text/html'], '<html>maintenance</html>');
    expect(fn() => SpeechResponseParser::parse($html, 'p', 'audio/mpeg'))->toThrow(InvalidResponseException::class);

    $audio = new Response(200, ['Content-Type' => 'audio/mpeg'], 'ID3bytes');
    expect(SpeechResponseParser::parse($audio, 'p', 'audio/mpeg')->audio->data)->toBe('ID3bytes');
});

it('accepts plain-text transcripts but rejects empty, null or HTML bodies', function (string $body, string $type, bool $valid) {
    $response = new Response(200, ['Content-Type' => $type], $body);

    if ($valid) {
        expect(TranscriptionResponseParser::parse($response, 'p')->transcript->text)->toBe($body);
    } else {
        expect(fn() => TranscriptionResponseParser::parse($response, 'p'))->toThrow(InvalidResponseException::class);
    }
})->with([
    'srt text' => ["1\n00:00:00,000 --> 00:00:01,000\nHello\n", 'text/plain', true],
    'empty' => ['', 'application/json', false],
    'null' => ['null', 'application/json', false],
    'html' => ['<html>nope</html>', 'text/html', false],
    'broken json' => ['{"text":', 'application/json', false],
]);
