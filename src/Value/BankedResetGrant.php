<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/** A cedar_ember grant can contain more than one banked reset. */
final readonly class BankedResetGrant
{
    /**
     * @param  list<string>  $clears
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public string $id,
        public int $resetsTotal,
        public int $resetsLeft,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public array $clears,
        public bool $paused,
        public bool $usableNow,
        public bool $useRequiresLimit,
        private array $raw,
    ) {}

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): ?self
    {
        $id = $raw['id'] ?? null;
        $left = $raw['resets_left'] ?? null;

        if (! is_string($id) || preg_match('/\A[a-z0-9_-]{1,40}\z/', $id) !== 1
            || ! is_int($left) || $left < 0) {
            return null;
        }

        $total = is_int($raw['resets_total'] ?? null) ? max(0, $raw['resets_total']) : 0;

        return new self(
            id: $id,
            resetsTotal: $total,
            resetsLeft: min($left, $total),
            startsAt: self::date($raw['starts_at'] ?? null),
            endsAt: self::date($raw['ends_at'] ?? null),
            clears: is_array($raw['clears'] ?? null) ? array_values(array_filter($raw['clears'], is_string(...))) : [],
            paused: ($raw['paused'] ?? false) === true,
            usableNow: ($raw['usable_now'] ?? false) === true,
            useRequiresLimit: ($raw['use_requires_limit'] ?? true) !== false,
            raw: $raw,
        );
    }

    /** Identifies one consume attempt within a grant, rather than the entire grant. */
    public function creditId(): string
    {
        return $this->id.'~'.$this->resetsLeft;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
            }
        }

        return null;
    }
}
