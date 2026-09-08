<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * The typed view of a
 * `POST /api/organizations/{uuid}/reset_rate_limits` response.
 *
 * Everything the API reports is kept in `raw`; only the stable core is
 * promoted to properties.
 */
final readonly class LimitResetResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public LimitResetOutcome $outcome,
        public ?\DateTimeImmutable $nextAvailableAt,
        public ?\DateTimeImmutable $weeklyResetsAt,
        private array $raw = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            outcome: LimitResetOutcome::fromString($raw['result'] ?? null),
            nextAvailableAt: self::date($raw['next_available_at'] ?? null),
            weeklyResetsAt: self::date($raw['weekly_resets_at'] ?? null),
            raw: $raw,
        );
    }

    /**
     * The session limit was reset. Usage still counts toward the weekly
     * limit, exactly as `/limit-reset` promises.
     */
    public function didReset(): bool
    {
        return $this->outcome === LimitResetOutcome::Reset;
    }

    /**
     * This week's reset was already spent. `nextAvailableAt` says when the
     * next one becomes available.
     */
    public function spent(): bool
    {
        return $this->outcome === LimitResetOutcome::AlreadyUsed;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (is_numeric($value)) {
            $timestamp = (float) $value;

            if ($timestamp > 10_000_000_000) {
                $timestamp /= 1000;
            }

            return (new \DateTimeImmutable())->setTimestamp((int) $timestamp);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return new \DateTimeImmutable(trim($value));
            } catch (\Exception) {
            }
        }

        return null;
    }
}
