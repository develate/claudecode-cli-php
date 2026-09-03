<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Message;

final readonly class UserMessage implements Message
{
    /** @param list<mixed> $content @param array<string, mixed> $raw */
    public function __construct(
        public array $content,
        public ?string $parentToolUseId,
        public ?string $sessionId,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
