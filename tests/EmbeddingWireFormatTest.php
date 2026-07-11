<?php

declare(strict_types=1);

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\OpenAICompatible\EmbeddingRequestBuilder;
use AiSdk\OpenAICompatible\EmbeddingResponseParser;
use AiSdk\Requests\EmbeddingRequest;

it('builds an OpenAI-compatible embedding request', function () {
    $request = new EmbeddingRequest(
        inputs: ['First document', 'Second document'],
        dimensions: 256,
        providerOptions: ['openai' => ['user' => 'user-123']],
    );

    expect(EmbeddingRequestBuilder::build('text-embedding-3-small', 'openai', $request))->toBe([
        'model' => 'text-embedding-3-small',
        'input' => ['First document', 'Second document'],
        'encoding_format' => 'float',
        'dimensions' => 256,
        'user' => 'user-123',
    ]);
});

it('supports provider-specific embedding request field names', function () {
    $request = new EmbeddingRequest(
        inputs: ['A document'],
        dimensions: 512,
        providerOptions: ['voyageai' => ['input_type' => 'document']],
    );

    expect(EmbeddingRequestBuilder::build('voyage-4', 'voyageai', $request, [
        'dimensionsParameter' => 'output_dimension',
        'includeEncodingFormat' => false,
    ]))->toBe([
        'model' => 'voyage-4',
        'input' => ['A document'],
        'output_dimension' => 512,
        'input_type' => 'document',
    ]);
});

it('parses ordered embedding vectors and usage', function () {
    $response = EmbeddingResponseParser::parse([
        'object' => 'list',
        'model' => 'text-embedding-3-small',
        'data' => [
            ['object' => 'embedding', 'index' => 1, 'embedding' => [0.3, 0.4]],
            ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]],
        ],
        'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
    ], 'openai', 2);

    expect($response->first()?->vector)->toBe([0.1, 0.2])
        ->and($response->embeddings[1]->vector)->toBe([0.3, 0.4])
        ->and($response->usage->inputTokens)->toBe(8)
        ->and($response->providerMetadata['openai']['model'])->toBe('text-embedding-3-small');
});

it('treats total-only embedding usage as input usage', function () {
    $response = EmbeddingResponseParser::parse([
        'data' => [['index' => 0, 'embedding' => [0.1]]],
        'usage' => ['total_tokens' => 5],
    ], 'voyageai');

    expect($response->usage->inputTokens)->toBe(5)
        ->and($response->usage->totalTokens)->toBe(5);
});

it('rejects missing or non-numeric embedding vectors', function () {
    EmbeddingResponseParser::parse([
        'data' => [['index' => 0, 'embedding' => 'base64-data']],
    ], 'provider');
})->throws(InvalidResponseException::class);

it('rejects incomplete embedding batches', function () {
    EmbeddingResponseParser::parse([
        'data' => [['index' => 0, 'embedding' => [0.1]]],
    ], 'provider', 2);
})->throws(InvalidResponseException::class, 'unexpected number of embeddings');

it('rejects invalid embedding indices', function () {
    EmbeddingResponseParser::parse([
        'data' => [
            ['index' => 0, 'embedding' => [0.1]],
            ['index' => 0, 'embedding' => [0.2]],
        ],
    ], 'provider', 2);
})->throws(InvalidResponseException::class, 'invalid embedding indices');

it('rejects missing indices for expected embedding batches', function () {
    EmbeddingResponseParser::parse([
        'data' => [
            ['index' => 0, 'embedding' => [0.1]],
            ['embedding' => [0.2]],
        ],
    ], 'provider', 2);
})->throws(InvalidResponseException::class, 'invalid embedding indices');
