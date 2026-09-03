<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

final readonly class TextDelta implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $text,
        public ?string $parentToolUseId,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
