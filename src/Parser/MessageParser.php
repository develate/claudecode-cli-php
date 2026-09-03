<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Parser;

use Develate\ClaudecodeCli\Content\ContentBlock;
use Develate\ClaudecodeCli\Message\AssistantMessage;
use Develate\ClaudecodeCli\Message\Message;
use Develate\ClaudecodeCli\Message\ResultMessage;
use Develate\ClaudecodeCli\Message\SystemMessage;
use Develate\ClaudecodeCli\Message\UnknownMessage;
use Develate\ClaudecodeCli\Message\UserMessage;
use Develate\ClaudecodeCli\Value\Usage;

final readonly class MessageParser
{
    public function __construct(private ContentBlockParser $contentBlockParser = new ContentBlockParser())
    {
    }

    /** @param array<string, mixed> $raw */
    public function parse(array $raw): Message
    {
        $type = self::string($raw['type'] ?? null);

        return match ($type) {
            'system' => $this->system($raw),
            'assistant' => $this->assistant($raw),
            'user' => $this->user($raw),
            'result' => $this->result($raw),
            default => new UnknownMessage($type, $raw),
        };
    }

    /** @param array<string, mixed> $raw */
    private function system(array $raw): SystemMessage
    {
        return new SystemMessage(
            self::string($raw['subtype'] ?? null),
            self::nullableString($raw['session_id'] ?? null),
            $raw,
            $raw,
        );
    }

    /** @param array<string, mixed> $raw */
    private function assistant(array $raw): AssistantMessage
    {
        $message = is_array($raw['message'] ?? null) ? $raw['message'] : [];
        $content = [];
        $blocks = is_array($message['content'] ?? null) ? $message['content'] : [];
        if (isset($blocks['type'])) {
            $blocks = [$blocks];
        }
        foreach ($blocks as $block) {
            if (is_string($block)) {
                $block = ['type' => 'text', 'text' => $block];
            }
            if (is_array($block)) {
                $content[] = $this->contentBlockParser->parse($block);
            }
        }
        $usage = is_array($message['usage'] ?? null) ? Usage::fromArray($message['usage']) : null;

        return new AssistantMessage(
            self::nullableString($message['model'] ?? null),
            $content,
            $usage,
            self::nullableString($raw['parent_tool_use_id'] ?? $raw['parentToolUseId'] ?? null),
            self::nullableString($raw['session_id'] ?? null),
            self::nullableString($message['stop_reason'] ?? $raw['stop_reason'] ?? null),
            $raw,
        );
    }

    /** @param array<string, mixed> $raw */
    private function user(array $raw): UserMessage
    {
        $message = is_array($raw['message'] ?? null) ? $raw['message'] : [];
        $content = $message['content'] ?? [];
        if (!is_array($content)) {
            $content = [$content];
        } elseif (isset($content['type'])) {
            $content = [$content];
        }

        return new UserMessage(
            array_values($content),
            self::nullableString($raw['parent_tool_use_id'] ?? $raw['parentToolUseId'] ?? null),
            self::nullableString($raw['session_id'] ?? null),
            $raw,
        );
    }

    /** @param array<string, mixed> $raw */
    private function result(array $raw): ResultMessage
    {
        $modelUsage = $raw['modelUsage'] ?? $raw['model_usage'] ?? [];
        $denials = $raw['permission_denials'] ?? $raw['permissionDenials'] ?? [];
        $cost = $raw['total_cost_usd'] ?? $raw['estimated_cost_usd'] ?? null;

        return new ResultMessage(
            self::string($raw['subtype'] ?? null),
            (bool) ($raw['is_error'] ?? false),
            self::string($raw['session_id'] ?? null),
            self::nullableString($raw['result'] ?? null),
            Usage::fromArray(is_array($raw['usage'] ?? null) ? $raw['usage'] : null),
            is_array($modelUsage) ? $modelUsage : [],
            is_numeric($cost) ? (float) $cost : null,
            is_numeric($raw['num_turns'] ?? null) ? (int) $raw['num_turns'] : 0,
            self::nullableString($raw['stop_reason'] ?? null),
            self::nullableString($raw['terminal_reason'] ?? null),
            self::arrayList($denials),
            $raw['structured_output'] ?? null,
            $raw,
        );
    }

    /** @return list<array<string, mixed>> */
    private static function arrayList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
