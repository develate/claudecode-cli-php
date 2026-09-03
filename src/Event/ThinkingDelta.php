<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

/**
 * An incremental piece of a thinking block.
 *
 * Deliberately separate from `TextDelta`: a host that streams the two into the
 * same view would show the model's reasoning as its answer.
 */
final readonly class ThinkingDelta implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $thinking,
        public ?string $parentToolUseId,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
