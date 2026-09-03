<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Content;

final readonly class UnknownBlock implements ContentBlock
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
