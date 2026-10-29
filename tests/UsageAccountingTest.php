<?php

declare(strict_types=1);

use AiSdk\OpenAICompatible\ChatResponseParser;
use AiSdk\OpenAICompatible\ChatStreamParser;
use AiSdk\OpenAICompatible\ImageResponseParser;
use AiSdk\OpenAICompatible\ResponsesResponseParser;
use AiSdk\OpenAICompatible\ResponsesStreamParser;
use AiSdk\OpenAICompatible\Support\ChatUsage;
use AiSdk\OpenAICompatible\Support\ResponsesUsage;
use AiSdk\Streaming\FinishPart;

it('keeps inclusive chat inputs and cache subsets in responses and streams', function (array $usage, int $cached, ?int $written): void {
    $payload = ['choices' => [['message' => ['role' => 'assistant', 'content' => 'ok'], 'finish_reason' => 'stop']], 'usage' => $usage];
    $normal = ChatResponseParser::parse($payload, 'fixture')->usage;
    $parts = iterator_to_array(ChatStreamParser::parse([['event' => null, 'data' => json_encode($payload, JSON_THROW_ON_ERROR)]], 'fixture'));
    $finish = $parts[array_key_last($parts)];
    expect($finish)->toBeInstanceOf(FinishPart::class);
    foreach ([$normal, $finish->usage] as $value) {
        expect($value->inputTokens)->toBe(100)->and($value->outputTokens)->toBe(20)
            ->and($value->cachedInputTokens)->toBe($cached)->and($value->cacheWriteInputTokens)->toBe($written)
            ->and($value->reasoningTokens)->toBe(5)->and($value->totalTokens)->toBe(120);
    }
})->with([
    'OpenAI compatible subsets' => [['prompt_tokens' => 100, 'completion_tokens' => 20, 'prompt_tokens_details' => ['cached_tokens' => 60, 'cache_write_tokens' => 10], 'completion_tokens_details' => ['reasoning_tokens' => 5]], 60, 10],
    'DeepSeek inclusive prompt' => [['prompt_tokens' => 100, 'prompt_cache_hit_tokens' => 60, 'prompt_cache_miss_tokens' => 40, 'completion_tokens' => 20, 'completion_tokens_details' => ['reasoning_tokens' => 5]], 60, null],
    'DeepSeek hit plus miss fallback' => [['prompt_cache_hit_tokens' => 60, 'prompt_cache_miss_tokens' => 40, 'completion_tokens' => 20, 'completion_tokens_details' => ['reasoning_tokens' => 5]], 60, null],
    'Moonshot top level cache' => [['prompt_tokens' => 100, 'cached_tokens' => 60, 'completion_tokens' => 20, 'completion_tokens_details' => ['reasoning_tokens' => 5]], 60, null],
]);

it('distinguishes unreported and reported zero subsets', function (): void {
    foreach ([ChatUsage::class, ResponsesUsage::class] as $parser) {
        $missing = $parser::fromArray([]);
        expect($missing->cachedInputTokens)->toBeNull()->and($missing->reasoningTokens)->toBeNull()->and($missing->cacheWriteInputTokens)->toBeNull();
        $zero = $parser::fromArray(['input_tokens' => 100, 'output_tokens' => 20, 'total_tokens' => 999, 'input_tokens_details' => ['cached_tokens' => 0, 'cache_write_tokens' => 0], 'output_tokens_details' => ['reasoning_tokens' => 0]]);
        expect($zero->inputTokens)->toBe(100)->and($zero->cachedInputTokens)->toBe(0)->and($zero->cacheWriteInputTokens)->toBe(0)->and($zero->reasoningTokens)->toBe(0)->and($zero->totalTokens)->toBe(999);
    }
});

it('reads Groq final streaming usage from x_groq', function (): void {
    $payload = ['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'x_groq' => ['usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'prompt_tokens_details' => ['cached_tokens' => 60], 'completion_tokens_details' => ['reasoning_tokens' => 5]]]];
    $parts = iterator_to_array(ChatStreamParser::parse([['event' => null, 'data' => json_encode($payload, JSON_THROW_ON_ERROR)]], 'groq'));
    $usage = $parts[array_key_last($parts)]->usage;
    expect($usage->inputTokens)->toBe(100)->and($usage->cachedInputTokens)->toBe(60)->and($usage->reasoningTokens)->toBe(5)->and($usage->totalTokens)->toBe(120);
});

it('preserves Responses subsets on both paths', function (): void {
    $response = ['id' => 'fixture', 'status' => 'completed', 'output' => [], 'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'input_tokens_details' => ['cached_tokens' => 60, 'cache_write_tokens' => 10], 'output_tokens_details' => ['reasoning_tokens' => 5]]];
    $normal = ResponsesResponseParser::parse($response, 'fixture')->usage;
    $parts = iterator_to_array(ResponsesStreamParser::parse([['event' => null, 'data' => json_encode(['type' => 'response.completed', 'response' => $response], JSON_THROW_ON_ERROR)]], 'fixture'));
    foreach ([$normal, $parts[array_key_last($parts)]->usage] as $usage) {
        expect($usage->inputTokens)->toBe(100)->and($usage->outputTokens)->toBe(20)->and($usage->cachedInputTokens)->toBe(60)->and($usage->cacheWriteInputTokens)->toBe(10)->and($usage->reasoningTokens)->toBe(5)->and($usage->totalTokens)->toBe(120);
    }
});

it('preserves image cache usage without adding it to input again', function (): void {
    $response = ImageResponseParser::parse(['data' => [['url' => 'https://example.com/image.png']], 'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'input_tokens_details' => ['cached_tokens' => 60]]], 'fixture');
    expect($response->usage->inputTokens)->toBe(100)->and($response->usage->cachedInputTokens)->toBe(60)->and($response->usage->totalTokens)->toBe(120);
});
