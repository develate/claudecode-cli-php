<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Exception\ProcessFailed;
use Develate\ClaudecodeCli\Event\Event;
use Develate\ClaudecodeCli\Message\Message;
use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\Transport\RunRequest;
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
            $stream = $this->transport->stream($this->withCancellation($this->request));
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

    private function withCancellation(RunRequest $request): RunRequest
    {
        return new RunRequest(
            $request->mode,
            $request->sessionId,
            $request->prompt,
            $request->cwd,
            $request->model,
            $request->permissionMode,
            $request->additionalDirectories,
            $request->tools,
            $request->allowedTools,
            $request->disallowedTools,
            $request->maxTurns,
            $request->maxBudgetUsd,
            $request->schema,
            $request->images,
            $request->timeout,
            fn (): bool => $this->cancelled,
        );
    }
}
