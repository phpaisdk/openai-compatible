<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Capability;
use AiSdk\Contracts\BaseModel;
use AiSdk\Contracts\TextModelInterface;
use AiSdk\Requests\TextModelRequest;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Sdk;
use AiSdk\Utils\Support\Url;
use Generator;

/**
 * Shared execution for providers that deliberately use the compatible Chat
 * Completions profile. Provider packages retain their own credentials and
 * model facades; they only supply the endpoint, headers, and profile here.
 */
final class CompatibleChatModel extends BaseModel implements TextModelInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<int, Capability> $capabilities
     */
    public function __construct(
        private readonly string $modelId,
        private readonly string $providerName,
        private readonly string $baseUrl,
        private readonly array $headers,
        private readonly ?Sdk $sdk,
        private readonly array $capabilities,
        private readonly ?ChatRequestProfile $profile = null,
    ) {}

    public function provider(): string
    {
        return $this->providerName;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function generate(TextModelRequest $request): TextModelResponse
    {
        $this->ensureTextRequestSupported($request, $this->capabilities);

        return ChatResponseParser::parse(
            $this->runner($this->sdk)->postJson(
                Url::joinPath($this->baseUrl, '/chat/completions'),
                ChatRequestBuilder::build($this->modelId, $this->provider(), $request, false, $this->profile),
                $this->headers,
                $this->provider(),
            ),
            $this->provider(),
        );
    }

    public function stream(TextModelRequest $request): Generator
    {
        $this->ensureTextRequestSupported($request, $this->capabilities, streaming: true);

        yield from ChatStreamParser::parse(
            $this->runner($this->sdk)->postStream(
                Url::joinPath($this->baseUrl, '/chat/completions'),
                ChatRequestBuilder::build($this->modelId, $this->provider(), $request, true, $this->profile),
                $this->headers,
                $this->provider(),
            ),
            $this->provider(),
        );
    }
}
