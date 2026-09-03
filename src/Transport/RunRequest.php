<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\RunOptions;
use Develate\ClaudecodeCli\SessionOptions;

final readonly class RunRequest
{
    public function __construct(
        public RunMode $mode,
        public ?string $sessionId,
        public string $prompt,
        public SessionOptions $session,
        public RunOptions $run = new RunOptions,
        public ?\Closure $isCancelled = null,
    ) {}

    public function cwd(): string
    {
        return $this->session->cwd;
    }

    public function timeout(): ?float
    {
        return $this->run->timeout;
    }

    public function withCancellation(\Closure $isCancelled): self
    {
        return new self($this->mode, $this->sessionId, $this->prompt, $this->session, $this->run, $isCancelled);
    }
}
