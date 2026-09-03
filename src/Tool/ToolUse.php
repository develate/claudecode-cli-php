<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tool;

use Develate\ClaudecodeCli\StreamItem;

final readonly class ToolUse implements StreamItem
{
    /** @param array<string, mixed> $input @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
        public ?string $parentToolUseId,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
