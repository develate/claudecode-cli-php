<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Parser;

use Develate\ClaudecodeCli\Content\ContentBlock;
use Develate\ClaudecodeCli\Content\TextBlock;
use Develate\ClaudecodeCli\Content\ThinkingBlock;
use Develate\ClaudecodeCli\Content\ToolUseBlock;
use Develate\ClaudecodeCli\Content\UnknownBlock;

final class ContentBlockParser
{
    /** @param array<string, mixed> $raw */
    public function parse(array $raw): ContentBlock
    {
        $type = is_string($raw['type'] ?? null) ? $raw['type'] : '';

        return match ($type) {
            'text' => new TextBlock(self::string($raw['text'] ?? null), $raw),
            'thinking' => new ThinkingBlock(
                self::string($raw['thinking'] ?? null),
                self::nullableString($raw['signature'] ?? null),
                $raw,
            ),
            'tool_use' => new ToolUseBlock(
                self::string($raw['id'] ?? null),
                self::string($raw['name'] ?? null),
                is_array($raw['input'] ?? null) ? $raw['input'] : [],
                $raw,
            ),
            default => new UnknownBlock($type, $raw),
        };
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
