<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Message;

use Develate\ClaudecodeCli\Value\Usage;

final readonly class ResultMessage implements Message
{
    /** @param array<string, mixed> $modelUsage @param list<array<string, mixed>> $permissionDenials @param array<string, mixed> $raw */
    public function __construct(
        public string $subtype,
        public bool $isError,
        public string $sessionId,
        public ?string $result,
        public Usage $usage,
        public array $modelUsage,
        public ?float $estimatedCostUsd,
        public int $numTurns,
        public ?string $stopReason,
        public ?string $terminalReason,
        public array $permissionDenials,
        public mixed $structuredOutput,
        private array $raw,
    ) {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
