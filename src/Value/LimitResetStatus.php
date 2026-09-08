<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * The typed view of the `juniper_tide` block in
 * `GET /api/oauth/usage?at_wall=1&skip_spend=1`.
 *
 * This is the availability behind Claude Code's `/limit-reset` command: a
 * session-limit reset that still counts toward the weekly limit and can be
 * claimed at most `resetsPerWeek` times per week. The plain `/api/oauth/usage`
 * response carries `"juniper_tide": null`, so only the `at_wall` variant is
 * a real availability signal.
 *
 * Everything the API reports is kept in `raw`; only the stable core is
 * promoted to properties.
 */
final readonly class LimitResetStatus
{
    /**
     * @param  array<string, mixed>  $eventProps
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $eligible,
        public ?LimitResetIneligibility $ineligibleReason,
        public bool $inExperiment,
        public ?LimitResetArm $arm,
        public bool $available,
        public ?\DateTimeImmutable $nextAvailableAt,
        public ?\DateTimeImmutable $weeklyResetsAt,
        public int $resetsPerWeek,
        public array $eventProps,
        private array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $reason = $raw['ineligible_reason'] ?? null;
        $arm = $raw['arm'] ?? null;

        return new self(
            eligible: (bool) ($raw['eligible'] ?? false),
            ineligibleReason: $reason === null ? null : LimitResetIneligibility::fromString($reason),
            inExperiment: (bool) ($raw['in_experiment'] ?? false),
            arm: is_string($arm) && $arm !== '' ? LimitResetArm::tryFrom(strtolower($arm)) : null,
            available: (bool) ($raw['available'] ?? false),
            nextAvailableAt: self::date($raw['next_available_at'] ?? null),
            weeklyResetsAt: self::date($raw['weekly_resets_at'] ?? null),
            resetsPerWeek: self::resetsPerWeek($raw['resets_per_week'] ?? null),
            eventProps: self::stringMap($raw['event_props'] ?? null),
            raw: $raw,
        );
    }

    /**
     * Whether a reset can currently be claimed: the account is eligible and
     * this week's reset has not been spent yet.
     */
    public function redeemable(): bool
    {
        return $this->eligible && $this->available;
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

    private static function resetsPerWeek(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 1;
        }

        return max(0, (int) $value);
    }

    /** @return array<string, mixed> */
    private static function stringMap(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
