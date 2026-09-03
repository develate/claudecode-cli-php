<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

class ProcessFactory
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env  merged onto the inherited environment;
     *                                            `false` removes an inherited variable
     */
    public function create(
        array $command,
        ?string $cwd = null,
        ?float $timeout = null,
        array $env = [],
        ?string $input = null,
    ): ClaudeProcess {
        return new ClaudeProcess($command, $cwd, $timeout, $env, $input);
    }
}
