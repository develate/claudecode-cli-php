<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Transport\Transport;
use Develate\ClaudecodeCli\Value\RunMetadata;

final class Session
{
    private ?Result $lastResult = null;

    private ?string $sessionId;

    /** @var list<callable(StreamItem): void> */
    private array $listeners = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly string $claudeVersion,
        private readonly SessionOptions $options,
        private readonly ?float $timeout = null,
        ?string $sessionId = null,
        private RunMode $nextMode = RunMode::Start,
        private readonly ?string $forkFromSessionId = null,
    ) {
        $this->sessionId = $sessionId ?? $options->sessionId;
    }

    public function id(): ?string
    {
        return $this->sessionId;
    }

    public function options(): SessionOptions
    {
        return $this->options;
    }

    public function query(string $prompt, ?RunOptions $options = null): Result
    {
        return $this->stream($prompt, $options)->result();
    }

    public function stream(string $prompt, ?RunOptions $options = null): Run
    {
        $run = $options ?? new RunOptions;

        if ($run->timeout === null && $this->timeout !== null) {
            $run = $run->withTimeout($this->timeout);
        }

        return new Run(
            transport: $this->transport,
            request: new RunRequest(
                mode: $this->nextMode,
                sessionId: $this->nextMode === RunMode::Fork ? $this->forkFromSessionId : $this->sessionId,
                prompt: $prompt,
                session: $this->options,
                run: $run,
            ),
            metadata: new RunMetadata($this->claudeVersion, $this->options->model, $this->options->cwd),
            listeners: $this->listeners,
            onComplete: function (Result $result): void {
                $this->lastResult = $result;
                if ($result->sessionId !== '') {
                    $this->sessionId = $result->sessionId;
                    $this->nextMode = RunMode::Resume;
                }
            },
        );
    }

    public function result(): Result
    {
        return $this->lastResult ?? throw new \LogicException('This session has no completed result yet.');
    }

    public function fork(): self
    {
        if ($this->sessionId === null || $this->sessionId === '') {
            throw new \LogicException('A session can only be forked after it has an id.');
        }

        return new self(
            transport: $this->transport,
            claudeVersion: $this->claudeVersion,
            options: $this->options->withSessionId(null),
            timeout: $this->timeout,
            sessionId: null,
            nextMode: RunMode::Fork,
            forkFromSessionId: $this->sessionId,
        );
    }

    /**
     * A copy of this session that runs with different options.
     *
     * The conversation is kept: only what the next run is told about it changes.
     */
    public function with(SessionOptions $options): self
    {
        return new self(
            transport: $this->transport,
            claudeVersion: $this->claudeVersion,
            options: $options,
            timeout: $this->timeout,
            sessionId: $this->sessionId,
            nextMode: $this->nextMode,
            forkFromSessionId: $this->forkFromSessionId,
        );
    }

    /** @param callable(StreamItem): void $listener */
    public function onItem(callable $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }
}
