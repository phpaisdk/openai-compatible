<?php

declare(strict_types=1);

use AiSdk\FinishReason;
use AiSdk\OpenAICompatible\ChatResponseParser;
use AiSdk\OpenAICompatible\ChatStreamParser;
use AiSdk\OpenAICompatible\ResponsesResponseParser;
use AiSdk\OpenAICompatible\ResponsesStreamParser;

it('preserves unknown chat finish reasons in synchronous and streamed metadata', function () {
    $payload = ['id' => 'response-1', 'model' => 'resolved-model', 'choices' => [['message' => ['content' => 'ok'], 'delta' => ['content' => 'ok'], 'finish_reason' => 'future_reason']]];
    $response = ChatResponseParser::parse($payload, 'test');
    $parts = iterator_to_array(ChatStreamParser::parse([['event' => null, 'data' => json_encode($payload)]], 'test'));
    $finish = end($parts);
    expect($response->finishReason)->toBe(FinishReason::Unknown)
        ->and($response->response->rawFinishReason)->toBe('future_reason')
        ->and($finish->response)->toEqual($response->response);
});

it('preserves responses API incomplete reasons in both paths', function () {
    $payload = ['id' => 'r', 'model' => 'm', 'status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'], 'output' => []];
    $response = ResponsesResponseParser::parse($payload, 'test');
    $parts = iterator_to_array(ResponsesStreamParser::parse([['event' => null, 'data' => json_encode(['type' => 'response.incomplete', 'response' => $payload])]], 'test'));
    expect(end($parts)->response)->toEqual($response->response)
        ->and($response->response->rawFinishReason)->toBe('max_output_tokens');
});
