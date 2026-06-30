# aisdk/openai-compatible

Shared OpenAI-compatible chat-completions wire adapter for the PHP AI SDK. Reusable by any provider that speaks the OpenAI `/chat/completions` REST/SSE protocol.

## Installation

```bash
composer require aisdk/openai-compatible
```

## What This Package Does

This package provides the shared wire-format bridge between core portable contracts (`AiSdk\*`) and the OpenAI-compatible chat-completions shape. It is used by providers like Groq, xAI, OpenRouter, and others that implement the OpenAI-compatible API.

It owns:
- Request body building (`ChatRequestBuilder`)
- Response parsing (`ChatResponseParser`)
- SSE stream parsing (`ChatStreamParser`)
- Message conversion (`ChatMessageConverter`)
- Tool conversion (`ChatToolConverter`)
- Usage normalization (`ChatUsage`)
- Finish reason mapping (`MapsFinishReason`)

It does **not** own:
- Provider authentication
- Model catalogs
- Provider-specific quirks or fallback behavior

## Usage

This package is consumed by provider packages, not directly by end users. A provider that speaks OpenAI-compatible chat completions uses it like this:

```php
use AiSdk\OpenAICompatible\ChatRequestBuilder;
use AiSdk\OpenAICompatible\ChatResponseParser;
use AiSdk\OpenAICompatible\ChatStreamParser;

$body = ChatRequestBuilder::build($modelId, $providerName, $request, stream: false);
$payload = $this->runner()->postJson($url, $body, $headers, $providerName);
$response = ChatResponseParser::parse($payload, $providerName);
```

## Provider Integration

To build a provider on top of this package:

1. Depend on `aisdk/core` and `aisdk/openai-compatible`.
2. Create a provider class extending `BaseProvider`.
3. Create a text model extending `BaseModel` that calls `ChatRequestBuilder::build()`, `ChatResponseParser::parse()`, and `ChatStreamParser::parse()`.
4. Add provider-specific auth, base URL, headers, and model catalog.
5. Apply any provider-specific adaptations (e.g., structured output downgrades) after calling `ChatRequestBuilder::build()`.

## Testing

```bash
composer test
```

## Links

- [Core Package](https://github.com/phpaisdk/core)
- [Project Documentation](https://github.com/phpaisdk)
