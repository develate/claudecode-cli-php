<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

use Develate\ClaudecodeCli\Exception\ProcessCancelled;
use Develate\ClaudecodeCli\Exception\ProcessTimedOut;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOut;
use Symfony\Component\Process\Process;

final class ClaudeProcess
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env
     */
    public function __construct(
        private readonly array $command,
        private readonly ?string $cwd = null,
        private readonly ?float $timeout = null,
        private readonly array $env = [],
        private readonly ?string $input = null,
    ) {}

    /** @return \Generator<int, string, void, ProcessResult> */
    public function lines(?\Closure $isCancelled = null): \Generator
    {
        ClaudeExecutable::assertAvailable($this->command[0]);
        $process = new Process($this->command, $this->cwd, $this->env, $this->input, $this->timeout);
        $reader = new StreamJsonReader;
        $stderr = '';

        try {
            $process->start();
            do {
                if ($isCancelled !== null && $isCancelled()) {
                    $process->stop(0.2);
                    throw new ProcessCancelled('Claude Code run was cancelled.');
                }
                $process->checkTimeout();
                foreach ($reader->push($process->getIncrementalOutput()) as $line) {
                    yield $line;
                }
                $stderr .= $process->getIncrementalErrorOutput();
                if ($process->isRunning()) {
                    usleep(10_000);
                }
            } while ($process->isRunning());

            foreach ($reader->push($process->getIncrementalOutput()) as $line) {
                yield $line;
            }
            $stderr .= $process->getIncrementalErrorOutput();
        } catch (SymfonyProcessTimedOut $exception) {
            throw new ProcessTimedOut($exception->getMessage(), 0, $exception);
        } finally {
            if ($process->isRunning()) {
                $process->stop(0.2);
            }
        }

        if (($last = $reader->finish()) !== null) {
            yield $last;
        }

        return new ProcessResult($process->getExitCode() ?? 1, $stderr);
    }
}
