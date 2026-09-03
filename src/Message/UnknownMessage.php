<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Message;

final readonly class UnknownMessage implements Message
{
    /** @param array<string, mixed> $raw */
    public function __construct(public string $type, private array $raw)
    {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
