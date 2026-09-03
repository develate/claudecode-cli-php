<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

class ProcessFactory
{
    /** @param list<string> $command */
    public function create(array $command, ?string $cwd = null, ?float $timeout = null): ClaudeProcess
    {
        return new ClaudeProcess($command, $cwd, $timeout);
    }
}
