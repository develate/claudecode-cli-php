<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Content;

final readonly class ToolUseBlock implements ContentBlock
{
    /** @param array<string, mixed> $input @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
