<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

final readonly class ProcessResult
{
    public function __construct(public int $exitCode, public string $stderr)
    {
    }
}
