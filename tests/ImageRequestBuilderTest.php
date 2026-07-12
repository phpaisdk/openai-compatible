<?php

declare(strict_types=1);

use AiSdk\OpenAICompatible\ImageRequestBuilder;
use AiSdk\Requests\ImageRequest;

it('builds a default OpenAI-compatible image generation payload', function () {
    $body = ImageRequestBuilder::build('image-model', 'provider', new ImageRequest(
        prompt: 'A tiny house',
        count: 2,
        aspectRatio: '16:9',
    ));

    expect($body)->toMatchArray([
        'model' => 'image-model',
        'prompt' => 'A tiny house',
        'n' => 2,
        'response_format' => 'b64_json',
        'size' => '1536x1024',
    ]);
});

it('can build provider-specific image payload variants without duplicating builders', function () {
    $body = ImageRequestBuilder::build(
        'grok-imagine-image-quality',
        'xai',
        new ImageRequest(
            prompt: 'A city skyline',
            count: 1,
            aspectRatio: '16:9',
            providerOptions: ['xai' => ['raw' => ['resolution' => '2k']]],
        ),
        [
            'aspectRatioParameter' => 'aspect_ratio',
            'inferSizeFromAspectRatio' => false,
            'sizeParameter' => null,
        ],
    );

    expect($body)->toMatchArray([
        'model' => 'grok-imagine-image-quality',
        'prompt' => 'A city skyline',
        'n' => 1,
        'response_format' => 'b64_json',
        'aspect_ratio' => '16:9',
        'resolution' => '2k',
    ])->and($body)->not->toHaveKey('size');
});

it('can omit unsupported fields for constrained image endpoints', function () {
    $body = ImageRequestBuilder::build(
        'image-model',
        'ollama',
        new ImageRequest(
            prompt: 'A tiny robot',
            count: 2,
            size: '1024x1024',
            seed: 42,
            providerOptions: ['ollama' => ['raw' => ['quality' => 'high']]],
        ),
        [
            'includeCount' => false,
            'includeProviderOptions' => false,
            'seedParameter' => null,
        ],
    );

    expect($body)->toBe([
        'model' => 'image-model',
        'prompt' => 'A tiny robot',
        'response_format' => 'b64_json',
        'size' => '1024x1024',
    ]);
});
