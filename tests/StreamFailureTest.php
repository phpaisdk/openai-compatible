<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\Exceptions\OverloadedException;
use AiSdk\Exceptions\RateLimitException;
use AiSdk\OpenAICompatible\ChatStreamParser;
use AiSdk\OpenAICompatible\ResponsesStreamParser;

function events(string ...$data): array
{
    return array_map(static fn(string $item): array => ['event' => null, 'data' => $item], $data);
}

function delta(array $delta, ?string $finish = null): string
{
    return json_encode(['id' => 'x', 'model' => 'm', 'choices' => [['index' => 0, 'delta' => $delta, 'finish_reason' => $finish]]]);
}

it('fails a chat stream that ends without a finish reason or DONE', function () {
    expect(fn() => iterator_to_array(ChatStreamParser::parse(events(delta(['content' => 'partial'])), 'p'), false))
        ->toThrow(InvalidResponseException::class, 'may be incomplete');
    expect(fn() => iterator_to_array(ChatStreamParser::parse([], 'p'), false))->toThrow(InvalidResponseException::class);
});

it('accepts a chat stream that finishes with DONE or a finish reason', function () {
    expect(iterator_to_array(ChatStreamParser::parse(events(delta(['content' => 'a']), '[DONE]'), 'p'), false))->not->toBeEmpty();
    expect(iterator_to_array(ChatStreamParser::parse(events(delta(['content' => 'a'], 'stop')), 'p'), false))->not->toBeEmpty();
});

it('types errors sent inside a chat stream', function (array $error, string $class) {
    expect(fn() => iterator_to_array(ChatStreamParser::parse(events(json_encode(['error' => $error])), 'p'), false))->toThrow($class);
})->with([
    'rate limit' => [['type' => 'rate_limit_exceeded', 'message' => 'slow'], RateLimitException::class],
    'overloaded' => [['type' => 'overloaded_error', 'message' => 'busy'], OverloadedException::class],
    'unknown' => [['message' => 'odd'], InvalidResponseException::class],
]);

it('ignores malformed deltas instead of emitting PHP warnings', function () {
    set_error_handler(static fn(int $no, string $str) => throw new ErrorException($str));

    try {
        $parts = iterator_to_array(ChatStreamParser::parse(events(delta(['content' => ['x'], 'tool_calls' => 'bad']), '[DONE]'), 'p'), false);
    } finally {
        restore_error_handler();
    }

    expect($parts)->not->toBeEmpty();
});

it('fails a Responses stream that never completes and types its errors', function () {
    $delta = json_encode(['type' => 'response.output_text.delta', 'delta' => 'a']);

    expect(fn() => iterator_to_array(ResponsesStreamParser::parse(events($delta), 'p'), false))->toThrow(InvalidResponseException::class);
    expect(fn() => iterator_to_array(ResponsesStreamParser::parse(events(json_encode(['type' => 'response.failed', 'response' => ['error' => ['code' => 'rate_limit_exceeded', 'message' => 'slow']]])), 'p'), false))
        ->toThrow(RateLimitException::class);
});
