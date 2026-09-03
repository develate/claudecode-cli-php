<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

final readonly class StreamEvent implements Event
{
    /** @param array<string, mixed> $event @param array<string, mixed> $raw */
    public function __construct(
        public string $type,
        public array $event,
        public ?string $parentToolUseId,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
