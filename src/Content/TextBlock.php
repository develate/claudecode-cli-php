<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Content;

final readonly class TextBlock implements ContentBlock
{
    /** @param array<string, mixed> $raw */
    public function __construct(public string $text, private array $raw)
    {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
