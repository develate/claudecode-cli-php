<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

final readonly class ToolProgressEvent implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public ?string $toolUseId,
        public ?string $toolName,
        public mixed $progress,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
