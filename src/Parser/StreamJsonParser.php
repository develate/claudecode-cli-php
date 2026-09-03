<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Parser;

use Develate\ClaudecodeCli\Content\ToolUseBlock;
use Develate\ClaudecodeCli\Event\ApiRetryEvent;
use Develate\ClaudecodeCli\Event\PermissionDeniedEvent;
use Develate\ClaudecodeCli\Event\RateLimitEvent;
use Develate\ClaudecodeCli\Event\StreamEvent;
use Develate\ClaudecodeCli\Event\TextDelta;
use Develate\ClaudecodeCli\Event\ThinkingDelta;
use Develate\ClaudecodeCli\Event\ToolProgressEvent;
use Develate\ClaudecodeCli\Event\UnknownEvent;
use Develate\ClaudecodeCli\Exception\InvalidStreamJson;
use Develate\ClaudecodeCli\Message\AssistantMessage;
use Develate\ClaudecodeCli\Message\Message;
use Develate\ClaudecodeCli\Message\UserMessage;
use Develate\ClaudecodeCli\StreamItem;
use Develate\ClaudecodeCli\Tool\ToolResult;
use Develate\ClaudecodeCli\Tool\ToolUse;
use Develate\ClaudecodeCli\Value\PermissionDenial;
use Develate\ClaudecodeCli\Value\RateLimitInfo;

final readonly class StreamJsonParser
{
    public function __construct(private MessageParser $messageParser = new MessageParser())
    {
    }

    /** @return list<StreamItem> */
    public function parseLine(string $line): array
    {
        try {
            $raw = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidStreamJson($line, $exception);
        }
        if (!is_array($raw) || array_is_list($raw)) {
            throw new InvalidStreamJson($line);
        }

        $type = self::string($raw['type'] ?? null);
        $subtype = self::string($raw['subtype'] ?? null);
        if ($type === 'system' && in_array($subtype, ['api_retry', 'api_retry_event'], true)) {
            return [$this->apiRetry($raw)];
        }
        if (in_array($type, ['system', 'assistant', 'user', 'result'], true)) {
            return $this->messageItems($this->messageParser->parse($raw), $raw);
        }

        return match ($type) {
            'stream_event' => [$this->streamEvent($raw)],
            'rate_limit_event', 'rate_limit' => [$this->rateLimit($raw)],
            'api_retry', 'api_retry_event' => [$this->apiRetry($raw)],
            'tool_progress', 'tool_progress_event' => [$this->toolProgress($raw)],
            'permission_denied', 'permission_denial' => [$this->permissionDenied($raw)],
            default => isset($raw['message'])
                ? [$this->messageParser->parse($raw)]
                : [new UnknownEvent($type, $raw)],
        };
    }

    /** @param array<string, mixed> $raw @return list<StreamItem> */
    private function messageItems(Message $message, array $raw): array
    {
        $items = [$message];
        if ($message instanceof AssistantMessage) {
            foreach ($message->toolUses() as $block) {
                $items[] = $this->toolUse($block, $message);
            }
        }
        if ($message instanceof UserMessage) {
            foreach ($message->content as $block) {
                if (!is_array($block) || ($block['type'] ?? null) !== 'tool_result') {
                    continue;
                }
                $items[] = new ToolResult(
                    self::string($block['tool_use_id'] ?? null),
                    $block['content'] ?? null,
                    (bool) ($block['is_error'] ?? false),
                    $raw,
                );
            }
        }

        return $items;
    }

    private function toolUse(ToolUseBlock $block, AssistantMessage $message): ToolUse
    {
        return new ToolUse($block->id, $block->name, $block->input, $message->parentToolUseId, $block->raw());
    }

    /** @param array<string, mixed> $raw */
    private function streamEvent(array $raw): StreamEvent|TextDelta|ThinkingDelta
    {
        $event = is_array($raw['event'] ?? null) ? $raw['event'] : [];
        $delta = is_array($event['delta'] ?? null) ? $event['delta'] : [];
        $parentId = self::nullableString($raw['parent_tool_use_id'] ?? $raw['parentToolUseId'] ?? null);
        if (($event['type'] ?? null) === 'content_block_delta') {
            if (($delta['type'] ?? null) === 'text_delta') {
                return new TextDelta(self::string($delta['text'] ?? null), $parentId, $raw);
            }
            // `signature_delta` carries no readable text and stays a StreamEvent.
            if (($delta['type'] ?? null) === 'thinking_delta') {
                return new ThinkingDelta(self::string($delta['thinking'] ?? null), $parentId, $raw);
            }
        }

        return new StreamEvent(self::string($event['type'] ?? null), $event, $parentId, $raw);
    }

    /** @param array<string, mixed> $raw */
    private function rateLimit(array $raw): RateLimitEvent
    {
        $info = $raw['rate_limit_info'] ?? $raw['rateLimitInfo'] ?? $raw['rate_limit'] ?? $raw;

        return new RateLimitEvent(RateLimitInfo::fromArray(is_array($info) ? $info : $raw), $raw);
    }

    /** @param array<string, mixed> $raw */
    private function apiRetry(array $raw): ApiRetryEvent
    {
        $attempt = $raw['attempt'] ?? $raw['retry_attempt'] ?? 0;
        $delay = $raw['delay_ms'] ?? $raw['retry_delay_ms'] ?? null;

        return new ApiRetryEvent(
            is_numeric($attempt) ? (int) $attempt : 0,
            is_numeric($delay) ? (int) $delay : null,
            self::nullableString($raw['error'] ?? $raw['message'] ?? null),
            $raw,
        );
    }

    /** @param array<string, mixed> $raw */
    private function toolProgress(array $raw): ToolProgressEvent
    {
        return new ToolProgressEvent(
            self::nullableString($raw['tool_use_id'] ?? $raw['toolUseId'] ?? null),
            self::nullableString($raw['tool_name'] ?? $raw['toolName'] ?? null),
            $raw['progress'] ?? $raw['elapsed_time_seconds'] ?? null,
            $raw,
        );
    }

    /** @param array<string, mixed> $raw */
    private function permissionDenied(array $raw): PermissionDeniedEvent
    {
        $denial = is_array($raw['denial'] ?? null) ? $raw['denial'] : $raw;

        return new PermissionDeniedEvent(PermissionDenial::fromArray($denial), $raw);
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
