<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Content;

final readonly class ThinkingBlock implements ContentBlock
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $thinking,
        public ?string $signature,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
