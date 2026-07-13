# aisdk/openai-compatible

Shared OpenAI-compatible wire adapter for the PHP AI SDK. Reusable by any provider that speaks OpenAI-compatible Chat Completions, Responses, embedding, image generation, speech generation, or transcription APIs.

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
- Responses request body building (`ResponsesRequestBuilder`)
- Responses response parsing (`ResponsesResponseParser`)
- Responses SSE stream parsing (`ResponsesStreamParser`)
- Embedding request body building (`EmbeddingRequestBuilder`)
- Embedding response parsing (`EmbeddingResponseParser`)
- Image request body building (`ImageRequestBuilder`)
- Image response parsing (`ImageResponseParser`)
- Speech request body building (`SpeechRequestBuilder`)
- Speech response parsing (`SpeechResponseParser`)
- Multipart transcription request building (`TranscriptionRequestBuilder`)
- Transcription response parsing (`TranscriptionResponseParser`)
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

For Responses endpoints, use the separate Responses wire helpers rather than adapting a Chat Completions body:

```php
use AiSdk\OpenAICompatible\ResponsesRequestBuilder;
use AiSdk\OpenAICompatible\ResponsesResponseParser;

$body = ResponsesRequestBuilder::build($modelId, $providerName, $request, stream: false);
$payload = $this->runner()->postJson($url, $body, $headers, $providerName);
$response = ResponsesResponseParser::parse($payload, $providerName);
```

For streaming, pass parsed SSE events to `ResponsesStreamParser::parse()` in the same way Chat Completions providers use `ChatStreamParser`.

For embedding endpoints:

```php
use AiSdk\OpenAICompatible\EmbeddingRequestBuilder;
use AiSdk\OpenAICompatible\EmbeddingResponseParser;

$body = EmbeddingRequestBuilder::build($modelId, $providerName, $request);
$payload = $this->runner()->postJson($url, $body, $headers, $providerName);
$response = EmbeddingResponseParser::parse($payload, $providerName);
```

Providers can configure the dimensions field name or omit it, and can disable the default `encoding_format: float` field when their endpoint uses a different wire shape. Provider-namespaced options and `raw` values are applied after portable fields.

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

For OpenAI-compatible transcription endpoints:

```php
use AiSdk\OpenAICompatible\TranscriptionRequestBuilder;
use AiSdk\OpenAICompatible\TranscriptionResponseParser;

$multipart = TranscriptionRequestBuilder::build($modelId, $providerName, $request);
$httpRequest = $requestFactory->createRequest('POST', $url)
    ->withHeader('Content-Type', 'multipart/form-data; boundary='.$multipart['boundary'])
    ->withBody($streamFactory->createStream($multipart['body']));
$response = $runner->sendRequest($httpRequest, $providerName);
$parsed = TranscriptionResponseParser::parse($response, $providerName);
```

## Provider Integration

To build a provider on top of this package:

1. Depend on `aisdk/core` and `aisdk/openai-compatible`.
2. Create a provider class extending `BaseProvider`.
3. Create a text model extending `BaseModel` and select either the Chat Completions helpers or the Responses helpers for the provider endpoint.
4. Add provider-specific auth, base URL, headers, and adapter capabilities.
5. Apply any provider-specific adaptations (e.g., structured output downgrades) after calling `ChatRequestBuilder::build()`.

For embeddings, create a model implementing `EmbeddingModelInterface`, call `EmbeddingRequestBuilder::build()`, then parse the provider payload with `EmbeddingResponseParser::parse()`. For image generation, create an image model implementing `ImageModelInterface`, call `ImageRequestBuilder::build()`, then parse the provider payload with `ImageResponseParser::parse()`. For speech generation, create a speech model implementing `SpeechModelInterface`, call `SpeechRequestBuilder::build()`, then parse the raw audio response with `SpeechResponseParser::parse()`. For transcription, implement `TranscriptionModelInterface`, send the multipart body from `TranscriptionRequestBuilder`, then normalize the response with `TranscriptionResponseParser`. Provider packages still own authentication, endpoint paths, adapter capabilities, and public facades. Model IDs should pass through as opaque provider values instead of being maintained as a package-owned inventory.

## Testing

```bash
composer test
```

## Links

- [Core Package](https://github.com/phpaisdk/core)
- [Project Documentation](https://github.com/phpaisdk)
