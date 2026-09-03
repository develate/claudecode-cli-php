<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Message;

final readonly class SystemMessage implements Message
{
    /** @param array<string, mixed> $data @param array<string, mixed> $raw */
    public function __construct(
        public string $subtype,
        public ?string $sessionId,
        public array $data,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
