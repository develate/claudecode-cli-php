<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Transport\Transport;
use Develate\ClaudecodeCli\Value\PermissionMode;
use Develate\ClaudecodeCli\Value\RunMetadata;

final class Session
{
    private ?Result $lastResult = null;

    /** @var list<callable(StreamItem): void> */
    private array $listeners = [];

    /**
     * @param list<string> $additionalDirectories
     * @param list<string>|null $tools
     * @param list<string> $allowedTools
     * @param list<string> $disallowedTools
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly string $claudeVersion,
        private readonly string $cwd,
        private readonly ?string $model,
        private readonly PermissionMode $permissionMode,
        private readonly array $additionalDirectories = [],
        private readonly ?array $tools = null,
        private readonly array $allowedTools = [],
        private readonly array $disallowedTools = [],
        private readonly ?float $timeout = null,
        private ?string $sessionId = null,
        private RunMode $nextMode = RunMode::Start,
        private readonly ?string $forkFromSessionId = null,
    ) {
    }

    public function id(): ?string
    {
        return $this->sessionId;
    }

    /**
     * @param array<string, mixed>|null $schema
     * @param list<string> $images
     */
    public function query(
        string $prompt,
        ?int $maxTurns = null,
        ?float $maxBudgetUsd = null,
        ?array $schema = null,
        array $images = [],
        ?float $timeout = null,
    ): Result {
        return $this->stream($prompt, $maxTurns, $maxBudgetUsd, $schema, $images, $timeout)->result();
    }

    /**
     * @param array<string, mixed>|null $schema
     * @param list<string> $images
     */
    public function stream(
        string $prompt,
        ?int $maxTurns = null,
        ?float $maxBudgetUsd = null,
        ?array $schema = null,
        array $images = [],
        ?float $timeout = null,
    ): Run {
        if ($maxTurns !== null && $maxTurns < 1) {
            throw new \InvalidArgumentException('maxTurns must be at least 1.');
        }
        if ($maxBudgetUsd !== null && $maxBudgetUsd < 0) {
            throw new \InvalidArgumentException('maxBudgetUsd must not be negative.');
        }

        return new Run(
            transport: $this->transport,
            request: new RunRequest(
                mode: $this->nextMode,
                sessionId: $this->nextMode === RunMode::Fork ? $this->forkFromSessionId : $this->sessionId,
                prompt: $prompt,
                cwd: $this->cwd,
                model: $this->model,
                permissionMode: $this->permissionMode,
                additionalDirectories: $this->additionalDirectories,
                tools: $this->tools,
                allowedTools: $this->allowedTools,
                disallowedTools: $this->disallowedTools,
                maxTurns: $maxTurns,
                maxBudgetUsd: $maxBudgetUsd,
                schema: $schema,
                images: $images,
                timeout: $timeout ?? $this->timeout,
            ),
            metadata: new RunMetadata($this->claudeVersion, $this->model, $this->cwd),
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
            cwd: $this->cwd,
            model: $this->model,
            permissionMode: $this->permissionMode,
            additionalDirectories: $this->additionalDirectories,
            tools: $this->tools,
            allowedTools: $this->allowedTools,
            disallowedTools: $this->disallowedTools,
            timeout: $this->timeout,
            sessionId: null,
            nextMode: RunMode::Fork,
            forkFromSessionId: $this->sessionId,
        );
    }

    /** @param callable(StreamItem): void $listener */
    public function onItem(callable $listener): self
    {
        $this->listeners[] = $listener;

        return $this;
    }
}
