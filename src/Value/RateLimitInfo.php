<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class RateLimitInfo
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $status,
        public ?string $type,
        public ?float $utilization,
        public ?\DateTimeImmutable $resetsAt,
        public bool $usingOverage,
        private array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $reset = $raw['resets_at'] ?? $raw['resetsAt'] ?? null;
        $resetsAt = null;
        if (is_numeric($reset)) {
            $timestamp = (float) $reset;
            if ($timestamp > 10_000_000_000) {
                $timestamp /= 1000;
            }
            $resetsAt = (new \DateTimeImmutable())->setTimestamp((int) $timestamp);
        } elseif (is_string($reset) && $reset !== '') {
            try {
                $resetsAt = new \DateTimeImmutable($reset);
            } catch (\Exception) {
            }
        }
        $utilization = $raw['utilization'] ?? null;

        return new self(
            is_string($raw['status'] ?? null) ? $raw['status'] : 'unknown',
            is_string($raw['type'] ?? $raw['rateLimitType'] ?? null) ? ($raw['type'] ?? $raw['rateLimitType']) : null,
            is_numeric($utilization) ? (float) $utilization : null,
            $resetsAt,
            (bool) ($raw['using_overage'] ?? $raw['usingOverage'] ?? $raw['isUsingOverage'] ?? false),
            $raw,
        );
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
