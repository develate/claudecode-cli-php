<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

/**
 * Everything that describes one run rather than the session it belongs to.
 */
final readonly class RunOptions
{
    /**
     * @param  array<string, mixed>|null  $schema  JSON Schema for structured output
     * @param  list<string>  $images  absolute image paths offered to the model
     * @param  float|null  $maxBackgroundWait  seconds a result may wait for the
     *                                         background tasks it left running,
     *                                         after which the process is ended
     *                                         and the run keeps its results;
     *                                         null waits until the run times out
     */
    public function __construct(
        public ?int $maxTurns = null,
        public ?float $maxBudgetUsd = null,
        public ?array $schema = null,
        public array $images = [],
        public ?float $timeout = null,
        public ?float $maxBackgroundWait = null,
    ) {
        if ($maxTurns !== null && $maxTurns < 1) {
            throw new \InvalidArgumentException('maxTurns must be at least 1.');
        }
        if ($maxBudgetUsd !== null && $maxBudgetUsd < 0) {
            throw new \InvalidArgumentException('maxBudgetUsd must not be negative.');
        }
        if ($maxBackgroundWait !== null && $maxBackgroundWait < 0) {
            throw new \InvalidArgumentException('maxBackgroundWait must not be negative.');
        }
    }

    public function withTimeout(?float $timeout): self
    {
        return new self($this->maxTurns, $this->maxBudgetUsd, $this->schema, $this->images, $timeout, $this->maxBackgroundWait);
    }
}
