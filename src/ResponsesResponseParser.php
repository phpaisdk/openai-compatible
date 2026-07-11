<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible;

use AiSdk\Exceptions\InvalidResponseException;
use AiSdk\FinishReason;
use AiSdk\OpenAICompatible\Support\ResponsesUsage;
use AiSdk\Responses\Parts\ReasoningPart;
use AiSdk\Responses\Parts\TextPart;
use AiSdk\Responses\Parts\ToolCallPart;
use AiSdk\Responses\TextModelResponse;
use AiSdk\Support\Json;
use AiSdk\Support\Usage;

final class ResponsesResponseParser
{
    /** @param array<string, mixed> $payload */
    public static function parse(array $payload, string $providerName): TextModelResponse
    {
        self::ensureValid($payload, $providerName);

        $parts = [];

        foreach (($payload['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'message') {
                foreach (($item['content'] ?? []) as $content) {
                    if (! is_array($content)) {
                        continue;
                    }

                    if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                        $parts[] = new TextPart($content['text']);
                    }
                }
            }

            if (($item['type'] ?? null) === 'reasoning') {
                $reasoning = self::reasoningText($item);
                if ($reasoning !== '') {
                    $parts[] = new ReasoningPart($reasoning);
                }
            }

            if (($item['type'] ?? null) === 'function_call') {
                $arguments = $item['arguments'] ?? '{}';
                $decoded = is_string($arguments) ? Json::decodeValue($arguments) : $arguments;

                $parts[] = new ToolCallPart(
                    id: (string) ($item['call_id'] ?? $item['id'] ?? ''),
                    name: (string) ($item['name'] ?? ''),
                    arguments: is_array($decoded) ? $decoded : [],
                );
            }
        }

        $usage = isset($payload['usage']) && is_array($payload['usage'])
            ? ResponsesUsage::fromArray($payload['usage'])
            : Usage::empty();

        return new TextModelResponse(
            parts: $parts,
            finishReason: self::finishReason($payload),
            usage: $usage,
            rawResponse: $payload,
            providerMetadata: [$providerName => self::metadata($payload)],
        );
    }

    /** @param array<string, mixed> $payload */
    public static function finishReason(array $payload): FinishReason
    {
        if (($payload['status'] ?? null) === 'completed') {
            foreach (($payload['output'] ?? []) as $item) {
                if (is_array($item) && ($item['type'] ?? null) === 'function_call') {
                    return FinishReason::ToolCalls;
                }
            }

            return FinishReason::Stop;
        }

        $reason = $payload['incomplete_details']['reason'] ?? null;

        return match ($reason) {
            'max_output_tokens' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default => ($payload['status'] ?? null) === 'failed' ? FinishReason::Error : FinishReason::Unknown,
        };
    }

    /** @param array<string, mixed> $payload */
    private static function ensureValid(array $payload, string $providerName): void
    {
        $error = $payload['error'] ?? null;
        if (is_array($error) || is_string($error)) {
            $message = is_array($error) && is_string($error['message'] ?? null)
                ? $error['message']
                : (is_string($error) ? $error : "Provider [{$providerName}] returned a Responses API error.");

            throw InvalidResponseException::forProvider($providerName, $message, ['body' => $payload]);
        }

        if (! isset($payload['output']) || ! is_array($payload['output'])) {
            throw InvalidResponseException::forProvider($providerName, "Provider [{$providerName}] returned a Responses API payload without output.", ['body' => $payload]);
        }
    }

    /** @param array<string, mixed> $item */
    private static function reasoningText(array $item): string
    {
        $text = '';

        foreach (($item['summary'] ?? []) as $summary) {
            if (is_array($summary) && is_string($summary['text'] ?? null)) {
                $text .= $summary['text'];
            }
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function metadata(array $payload): array
    {
        $metadata = [];

        foreach (['id', 'object', 'created_at', 'model', 'status', 'service_tier'] as $key) {
            if (array_key_exists($key, $payload)) {
                $metadata[$key] = $payload[$key];
            }
        }

        return $metadata;
    }
}
