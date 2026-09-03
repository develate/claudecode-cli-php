<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class ModelUsage
{
    public function __construct(
        public string $model,
        public Usage $usage,
        public ?float $estimatedCostUsd = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(string $model, array $data): self
    {
        $cost = $data['costUSD'] ?? $data['cost_usd'] ?? $data['estimated_cost_usd'] ?? null;

        return new self(
            $model,
            Usage::fromArray($data),
            is_numeric($cost) ? (float) $cost : null,
        );
    }
}
