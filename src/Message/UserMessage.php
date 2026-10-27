<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Message;

final readonly class UserMessage implements Message
{
    /**
     * @param list<mixed> $content
     * @param array<string, mixed> $raw
     * @param bool $isReplay Claude Code echoing a message it read from stdin,
     *                       at the moment the model received it
     */
    public function __construct(
        public array $content,
        public ?string $parentToolUseId,
        public ?string $sessionId,
        private array $raw,
        public ?string $uuid = null,
        public bool $isReplay = false,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
