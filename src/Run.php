<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Exception\ProcessFailed;
use Develate\ClaudecodeCli\Event\Event;
use Develate\ClaudecodeCli\Message\Message;
use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Transport\SteeringChannel;
use Develate\ClaudecodeCli\Transport\Transport;
use Develate\ClaudecodeCli\Value\RunMetadata;

/** @implements \IteratorAggregate<int, StreamItem> */
final class Run implements \IteratorAggregate
{
    private bool $cancelled = false;
    private bool $iterating = false;
    private bool $completed = false;
    private ?Result $lastResult = null;
    private ?\Generator $execution = null;
    private ?\Throwable $failure = null;
    private readonly SteeringChannel $steering;

    /** @var list<StreamItem> */
    private array $items = [];

    /**
     * @param list<callable(StreamItem): void> $listeners
     * @param callable(Result): void $onComplete
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly RunRequest $request,
        private readonly RunMetadata $metadata,
        private readonly array $listeners,
        private readonly \Closure $onComplete,
    ) {
        $this->steering = new SteeringChannel;
    }

    public function getIterator(): \Traversable
    {
        if ($this->completed) {
            yield from $this->items;

            return;
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->iterating) {
            throw new \LogicException('A run cannot be iterated concurrently.');
        }

        $this->execution ??= $this->execute();
        $this->iterating = true;
        try {
            while ($this->execution->valid()) {
                yield $this->execution->current();
                $this->execution->next();
            }
        } finally {
            $this->iterating = false;
        }
    }

    public function result(): Result
    {
        if (!$this->completed) {
            foreach ($this as $_) {
            }
        }

        return $this->lastResult ?? throw $this->failure ?? new \LogicException('The run did not produce a result.');
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * Adds a user message to the turn while it runs.
     *
     * The model receives it at its next tool boundary, or as a follow-up in
     * the same run when no tool call is left. The returned uuid comes back on
     * the replayed `UserMessage` (`isReplay`) once the model has it.
     *
     * @param list<string> $images absolute image paths offered to the model
     * @param string|null $uuid your own id for the message, e.g. to correlate it
     */
    public function steer(string $prompt, array $images = [], ?string $uuid = null): string
    {
        return $this->steering->steer($prompt, $images, $uuid);
    }

    /**
     * Whether the run is still accepting steering messages.
     */
    public function isSteerable(): bool
    {
        return $this->steering->isOpen();
    }

    /**
     * Runs between reads while the process is up, at most every quarter of a
     * second and also while the model is silently thinking: the place to
     * deliver input arriving from elsewhere.
     *
     * @param (callable(): void)|null $handler
     */
    public function onTick(?callable $handler): self
    {
        $this->steering->onTick($handler);

        return $this;
    }

    public function isComplete(): bool
    {
        return $this->completed;
    }

    /** @return list<StreamItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return array_values(array_filter($this->items, static fn (StreamItem $item): bool => $item instanceof Message));
    }

    /** @return list<Event> */
    public function events(): array
    {
        return array_values(array_filter($this->items, static fn (StreamItem $item): bool => $item instanceof Event));
    }

    /** @return \Generator<int, StreamItem, void, void> */
    private function execute(): \Generator
    {
        $builder = new ResultBuilder($this->request->sessionId ?? '');
        try {
            $stream = $this->transport->stream(
                $this->request->withCancellation(fn (): bool => $this->cancelled)->withSteering($this->steering),
            );
            foreach ($stream as $item) {
                $builder->add($item);
                $this->items[] = $item;
                foreach ($this->listeners as $listener) {
                    $listener($item);
                }
                yield $item;
            }

            /** @var ProcessResult $process */
            $process = $stream->getReturn();
            $this->lastResult = $builder->build($this->metadata, $process->exitCode);
            if ($process->exitCode !== 0 && !$builder->hasResultMessage()) {
                throw new ProcessFailed($process->exitCode, $process->stderr);
            }
            ($this->onComplete)($this->lastResult);
            $this->completed = true;
        } catch (\Throwable $exception) {
            $this->failure = $exception;
            throw $exception;
        }
    }
}
