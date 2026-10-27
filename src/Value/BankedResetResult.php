<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class BankedResetResult
{
    /**
     * @param  list<string>  $cleared
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public BankedResetOutcome $outcome,
        public ?string $reason,
        public ?int $resetsLeft,
        public array $cleared,
        private array $raw,
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            outcome: BankedResetOutcome::fromString($raw['result'] ?? null),
            reason: is_string($raw['reason'] ?? null) ? $raw['reason'] : null,
            resetsLeft: is_int($raw['resets_left'] ?? null) ? max(0, $raw['resets_left']) : null,
            cleared: is_array($raw['cleared'] ?? null) ? array_values(array_filter($raw['cleared'], is_string(...))) : [],
            raw: $raw,
        );
    }

    public function didReset(): bool
    {
        return $this->outcome === BankedResetOutcome::Reset;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
