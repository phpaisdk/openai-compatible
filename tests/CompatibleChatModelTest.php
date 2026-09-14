<?php

declare(strict_types=1);

use AiSdk\Capability;
use AiSdk\OpenAICompatible\CompatibleChatModel;
use AiSdk\Support\Sdk;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

it('executes a compatible chat request from provider-owned connection values', function () {
    $client = new class implements ClientInterface {
        public ?RequestInterface $request = null;

        public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            $this->request = $request;

            return new Response(200, [], json_encode([
                'id' => 'chat_1',
                'choices' => [[
                    'message' => ['content' => 'Hello'],
                    'finish_reason' => 'stop',
                ]],
            ]));
        }
    };
    $factory = new Psr17Factory();
    $model = new CompatibleChatModel(
        modelId: 'example-model',
        providerName: 'example',
        baseUrl: 'https://example.test/v1',
        headers: ['Authorization' => 'Bearer test'],
        sdk: new Sdk($client, $factory, $factory),
        capabilities: [Capability::TextGeneration, Capability::Streaming, Capability::TextInput],
    );

    $result = $model->generate(new \AiSdk\Requests\TextModelRequest([
        \AiSdk\Message::user('Hi'),
    ]));

    expect($result->text())->toBe('Hello')
        ->and($client->request?->getUri()->getPath())->toBe('/v1/chat/completions')
        ->and($client->request?->getHeaderLine('Authorization'))->toBe('Bearer test');
});
