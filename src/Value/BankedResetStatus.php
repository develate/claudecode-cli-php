<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class BankedResetStatus
{
    /**
     * @param  list<BankedResetGrant>  $grants
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public bool $eligible,
        public ?string $ineligibleReason,
        public bool $atLimit,
        public ?string $nextGrantId,
        public array $grants,
        private array $raw,
    ) {}

    /**
     * Missing or malformed availability is unknown, rather than zero credits.
     *
     * @param  array<string, mixed>  $usage
     */
    public static function fromUsage(array $usage): ?self
    {
        $raw = $usage['cedar_ember'] ?? null;

        if (! is_array($raw) || ! is_bool($raw['eligible'] ?? null)) {
            return null;
        }

        $grants = [];
        foreach (is_array($raw['grants'] ?? null) ? $raw['grants'] : [] as $value) {
            $grant = is_array($value) ? BankedResetGrant::fromArray($value) : null;
            if ($grant !== null) {
                $grants[] = $grant;
            }
        }

        return new self(
            eligible: $raw['eligible'],
            ineligibleReason: is_string($raw['ineligible_reason'] ?? null) ? $raw['ineligible_reason'] : null,
            atLimit: ($raw['at_limit'] ?? false) === true,
            nextGrantId: is_string($raw['next_grant_id'] ?? null) ? $raw['next_grant_id'] : null,
            grants: $grants,
            raw: $raw,
        );
    }

    public function availableCount(): int
    {
        return array_sum(array_map(static fn (BankedResetGrant $grant): int => $grant->resetsLeft, $this->grants));
    }

    public function redeemableGrant(string $creditId, ?\DateTimeImmutable $now = null): ?BankedResetGrant
    {
        $now ??= new \DateTimeImmutable;
        $cooldown = $this->raw['cooldown_until'] ?? null;
        if (is_string($cooldown) && trim($cooldown) !== '') {
            try {
                if (new \DateTimeImmutable($cooldown) > $now) {
                    return null;
                }
            } catch (\Exception) {
                return null;
            }
        }

        foreach ($this->grants as $grant) {
            if ($this->eligible && $grant->id === $this->nextGrantId && $grant->creditId() === $creditId
                && $grant->resetsLeft > 0 && $grant->usableNow && ! $grant->paused
                && ($grant->startsAt === null || $grant->startsAt <= $now)
                && ($grant->endsAt === null || $grant->endsAt > $now)) {
                return $grant;
            }
        }

        return null;
    }

    /** @return array{available_count: int, credits: list<array<string, mixed>>} */
    public function summary(): array
    {
        $credits = [];
        foreach ($this->grants as $grant) {
            if ($grant->resetsLeft === 0) {
                continue;
            }

            $credits[] = [
                'id' => $grant->creditId(),
                'grant_id' => $grant->id,
                'resets_left' => $grant->resetsLeft,
                'status' => $this->eligible && $grant->id === $this->nextGrantId && $grant->usableNow && ! $grant->paused ? 'available' : 'unavailable',
                'expires_at' => $grant->endsAt?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
                'starts_at' => $grant->startsAt?->format(DATE_ATOM),
                'cooldown_until' => $this->raw['cooldown_until'] ?? null,
                'clears' => $grant->clears,
                'use_requires_limit' => $grant->useRequiresLimit,
            ];
        }

        return ['available_count' => $this->availableCount(), 'credits' => $credits];
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
