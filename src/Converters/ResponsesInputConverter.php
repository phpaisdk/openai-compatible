<?php

declare(strict_types=1);

namespace AiSdk\OpenAICompatible\Converters;

use AiSdk\Content;
use AiSdk\ContentSource;
use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Message;
use AiSdk\Support\Json;

final class ResponsesInputConverter
{
    /**
     * @param  array<int, Message>  $messages
     * @return array<int, array<string, mixed>>
     */
    public static function convert(array $messages): array
    {
        $input = [];

        foreach ($messages as $message) {
            if ($message->role === Message::ROLE_TOOL) {
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $message->toolCallId ?? '',
                    'output' => $message->text(),
                ];

                continue;
            }

            if ($message->role === Message::ROLE_ASSISTANT && $message->toolCalls !== []) {
                if ($message->text() !== '') {
                    $input[] = self::message($message);
                }

                foreach ($message->toolCalls as $toolCall) {
                    $input[] = [
                        'type' => 'function_call',
                        'call_id' => $toolCall->id,
                        'name' => $toolCall->name,
                        'arguments' => Json::encode($toolCall->arguments),
                    ];
                }

                continue;
            }

            $input[] = self::message($message);
        }

        return $input;
    }

    /** @return array<string, mixed> */
    private static function message(Message $message): array
    {
        return [
            'role' => $message->role,
            'content' => array_map(
                fn(Content $content): array => self::content($content, $message->role),
                $message->content,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private static function content(Content $content, string $role): array
    {
        return match ($content->type) {
            Content::TYPE_TEXT => [
                'type' => $role === Message::ROLE_ASSISTANT ? 'output_text' : 'input_text',
                'text' => (string) $content->textValue(),
            ],
            Content::TYPE_IMAGE => [
                'type' => 'input_image',
                'image_url' => $content->source() === ContentSource::Url
                    ? (string) $content->url()
                    : (string) $content->dataUri(),
            ],
            Content::TYPE_FILE => [
                'type' => 'input_file',
                'filename' => $content->filename() ?? 'input',
                'file_data' => $content->source() === ContentSource::Url
                    ? (string) $content->url()
                    : (string) $content->dataUri(),
            ],
            default => throw new InvalidArgumentException("Responses API does not support content type [{$content->type}] through this adapter."),
        };
    }
}
