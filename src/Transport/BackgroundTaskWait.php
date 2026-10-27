<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\Message\ResultMessage;
use Develate\ClaudecodeCli\Message\SystemMessage;
use Develate\ClaudecodeCli\Message\UserMessage;
use Develate\ClaudecodeCli\StreamItem;

/**
 * Decides when a run's stdin may close, and when a run waited long enough.
 *
 * The CLI reports a finished background task to the model, which answers
 * again with another result. Stdin closes after the first result that leaves
 * nothing running: Claude Code before 2.1.292 stopped its background tasks
 * once stdin closed after a result, and an open stdin keeps steering possible.
 *
 * Since 2.1.292 a closed stdin no longer stops anything; the CLI waits for the
 * tasks itself. So the wait limit is enforced by ending the process, once a
 * result has waited that long for tasks still running (see isExpired()).
 *
 * A user message the model receives after a result is answered with another
 * one, so it reopens the wait.
 */
final class BackgroundTaskWait
{
    private int $running = 0;

    private bool $settled = false;

    private ?float $waitingSince = null;

    /**
     * @param  float|null  $limit  seconds to wait after a result for background
     *                             tasks to finish; null waits for as long as the
     *                             run may take
     * @param  (\Closure(): float)|null  $clock
     */
    public function __construct(
        private readonly ?float $limit = null,
        private readonly ?\Closure $clock = null,
    ) {}

    public function observe(StreamItem $item): void
    {
        if ($item instanceof SystemMessage && $item->subtype === 'background_tasks_changed') {
            $tasks = $item->data['tasks'] ?? [];
            $this->running = is_array($tasks) ? count($tasks) : 0;
            // The model is answering the finished tasks now; that answer is
            // the run's own work, not waiting.
            if ($this->running === 0) {
                $this->waitingSince = null;
            }
        }

        if ($item instanceof UserMessage && $item->isReplay) {
            $this->settled = false;
            $this->waitingSince = null;
        }

        if ($item instanceof ResultMessage) {
            $this->settled = $this->running === 0;
            $this->waitingSince = $this->settled ? null : $this->now();
        }
    }

    public function isDone(): bool
    {
        return $this->settled || $this->isExpired();
    }

    /**
     * True once a result has waited the whole limit for background tasks that
     * are still running. The run has to be ended then: closing stdin alone no
     * longer stops them.
     */
    public function isExpired(): bool
    {
        return !$this->settled
            && $this->waitingSince !== null
            && $this->limit !== null
            && $this->now() - $this->waitingSince >= $this->limit;
    }

    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : microtime(true);
    }
}
