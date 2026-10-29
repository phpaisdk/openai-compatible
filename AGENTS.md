# aisdk/openai-compatible: guide for coding agents

The shared wire adapter between `aisdk/core` contracts and the OpenAI-style APIs (Chat Completions, Responses, embeddings, images, speech, transcription). Many provider packages (Groq, xAI, OpenRouter, Cerebras, ...) are thin shells over this package, so a change here affects all of them.

## What it owns

Request builders (`ChatRequestBuilder`, `ResponsesRequestBuilder`, `EmbeddingRequestBuilder`, `ImageRequestBuilder`, `SpeechRequestBuilder`, `TranscriptionRequestBuilder`), response and stream parsers, message/tool converters (`Converters/`), usage and finish-reason normalization, `MultipartFormData`, and `CompatibleChatModel`.

It does **not** own authentication, model inventories, or provider quirks beyond what a profile can express.

## Rules

1. **Provider differences are profiles, not forks.** Express parameter names and omissions through `ChatRequestProfile` / `ResponsesRequestProfile` (for example `maxTokensParameter`, `includeTemperature`, `omitTemperatureWhenReasoning`). If a provider needs behavior no profile can express, add a profile option; do not copy a builder into the provider.
2. **Omit what the caller did not set.** `max_tokens`/`max_completion_tokens`/`max_output_tokens`, `temperature` and `top_p` are written only when the `TextModelRequest` carries a non-null value.
3. **Never add a model catalog or validate model ids.** Ids are opaque and sent as given.
4. **Preserve unknown data.** Parsers must not silently drop unrecognized output parts or finish reasons; map to the closest `FinishReason` and keep the raw value in provider metadata where core allows.
5. **Streaming**: tool-call arguments arrive fragmented; assemble per slot via core's `StreamState`, never by string concatenation across calls.
6. Changes here need a regression test in `tests/` asserting the exact JSON body (builders) or the exact normalized result (parsers).

## Layout

`src/*Builder.php`, `src/*Parser.php`, `src/*Profile.php`, `src/Converters/`, `src/Support/`, `tests/` (Pest; `ChatWireFormatTest`, `ResponsesWireFormatTest` show the pattern).

## Commands

```bash
composer lint && composer analyze && composer test
```

Provider packages consume a released version of this package; bump their `aisdk/openai-compatible` constraint only when they need the change.

## Docs

https://phpaisdk.com/docs/openai-compatible-package.md · https://phpaisdk.com/llms.txt

## Changelog

Keep `CHANGELOG.md` current: add short, user-facing entries (what users can do now, dependency compatibility) for every release. Release notes on GitHub should match.
