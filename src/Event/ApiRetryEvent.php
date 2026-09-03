<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

final readonly class ApiRetryEvent implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public int $attempt,
        public ?int $delayMs,
        public ?string $error,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
