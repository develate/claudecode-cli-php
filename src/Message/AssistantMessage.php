<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Message;

use Develate\ClaudecodeCli\Content\ContentBlock;
use Develate\ClaudecodeCli\Content\TextBlock;
use Develate\ClaudecodeCli\Content\ThinkingBlock;
use Develate\ClaudecodeCli\Content\ToolUseBlock;
use Develate\ClaudecodeCli\Value\Usage;

final readonly class AssistantMessage implements Message
{
    /** @param list<ContentBlock> $content @param array<string, mixed> $raw */
    public function __construct(
        public ?string $model,
        public array $content,
        public ?Usage $usage,
        public ?string $parentToolUseId,
        public ?string $sessionId,
        public ?string $stopReason,
        private array $raw,
    ) {
    }

    public function text(): string
    {
        return implode('', array_map(
            static fn (TextBlock $block): string => $block->text,
            array_values(array_filter($this->content, static fn (ContentBlock $block): bool => $block instanceof TextBlock)),
        ));
    }

    /** @return list<ThinkingBlock> */
    public function thinking(): array
    {
        return $this->ofType(ThinkingBlock::class);
    }

    /** @return list<ToolUseBlock> */
    public function toolUses(): array
    {
        return $this->ofType(ToolUseBlock::class);
    }

    public function raw(): array
    {
        return $this->raw;
    }

    /** @template T of ContentBlock @param class-string<T> $class @return list<T> */
    private function ofType(string $class): array
    {
        return array_values(array_filter($this->content, static fn (ContentBlock $block): bool => $block instanceof $class));
    }
}
