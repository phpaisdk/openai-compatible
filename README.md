# aisdk/openai-compatible

Shared OpenAI-compatible wire adapter for the PHP AI SDK. Reusable by any provider that speaks OpenAI-compatible chat completions, image generation, or speech generation APIs.

## Installation

```bash
composer require aisdk/openai-compatible
```

## What This Package Does

This package provides the shared wire-format bridge between core portable contracts (`AiSdk\*`) and OpenAI-compatible provider shapes. It is used by providers like Groq, xAI, OpenRouter, and others that implement the OpenAI-compatible API.

It owns:
- Request body building (`ChatRequestBuilder`)
- Response parsing (`ChatResponseParser`)
- SSE stream parsing (`ChatStreamParser`)
- Image request body building (`ImageRequestBuilder`)
- Image response parsing (`ImageResponseParser`)
- Speech request body building (`SpeechRequestBuilder`)
- Speech response parsing (`SpeechResponseParser`)
- Message conversion (`ChatMessageConverter`)
- Tool conversion (`ChatToolConverter`)
- Usage normalization (`ChatUsage`)
- Finish reason mapping (`MapsFinishReason`)

It does **not** own:
- Provider authentication
- Model inventories
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

For image generation endpoints:

```php
use AiSdk\OpenAICompatible\ImageRequestBuilder;
use AiSdk\OpenAICompatible\ImageResponseParser;

$body = ImageRequestBuilder::build($modelId, $providerName, $request);
$payload = $this->runner()->postJson($url, $body, $headers, $providerName);
$response = ImageResponseParser::parse($payload, $providerName);
```

For speech generation endpoints:

```php
use AiSdk\OpenAICompatible\SpeechRequestBuilder;
use AiSdk\OpenAICompatible\SpeechResponseParser;

$body = SpeechRequestBuilder::build($modelId, $providerName, $request);
$response = $this->runner()->postRaw($url, $body, $headers, $providerName);
$parsed = SpeechResponseParser::parse($response, $providerName, 'audio/mpeg');
```

## Provider Integration

To build a provider on top of this package:

1. Depend on `aisdk/core` and `aisdk/openai-compatible`.
2. Create a provider class extending `BaseProvider`.
3. Create a text model extending `BaseModel` that calls `ChatRequestBuilder::build()`, `ChatResponseParser::parse()`, and `ChatStreamParser::parse()`.
4. Add provider-specific auth, base URL, headers, and adapter capabilities.
5. Apply any provider-specific adaptations (e.g., structured output downgrades) after calling `ChatRequestBuilder::build()`.

For image generation, create an image model implementing `ImageModelInterface`, call `ImageRequestBuilder::build()`, then parse the provider payload with `ImageResponseParser::parse()`. For speech generation, create a speech model implementing `SpeechModelInterface`, call `SpeechRequestBuilder::build()`, then parse the raw audio response with `SpeechResponseParser::parse()`. Provider packages still own authentication, endpoint paths, adapter capabilities, and public facades. Model IDs should pass through as opaque provider values instead of being maintained as a package-owned inventory.

## Testing

```bash
composer test
```

## Links

- [Core Package](https://github.com/phpaisdk/core)
- [Project Documentation](https://github.com/phpaisdk)
