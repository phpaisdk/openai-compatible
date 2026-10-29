# Changelog

Notable changes to `aisdk/openai-compatible`. Release notes are also published at https://github.com/phpaisdk/openai-compatible/releases.

## Unreleased (v0.10.2)

- Preserve cached, cache-write and reasoning token subsets, including DeepSeek and Moonshot cache fields and Groq streaming usage.

## v0.10.1 — 2026-10-29

- Chat and Responses streams that end before finishing now fail instead of looking complete; in-stream errors are typed; malformed deltas are ignored.
- Speech endpoints that return JSON or HTML, empty or malformed transcripts, and malformed image data raise `InvalidResponseException`.
- Raise SDK exceptions instead of native PHP ones.

## v0.10.0 — 2026-10-29

### What's new

- Stored provider files can be used as document and image inputs in OpenAI-compatible Chat and Responses requests, with shared file upload, retrieval, listing, download and deletion services for provider packages.
- Response id, resolved model and raw finish reason are preserved in Chat and Responses results.
- OpenAI profiles forward the prompt-cache key when you set a `PromptCache`.
- Unset generation options are omitted so each provider's own defaults apply.

### Compatibility

- Requires `aisdk/core ^0.10.0`. Upgrade SDK packages together.

## Earlier releases

See the [GitHub releases](https://github.com/phpaisdk/openai-compatible/releases) for versions before 0.10.0.
