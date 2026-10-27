<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

use Develate\ClaudecodeCli\Exception\ProcessCancelled;
use Develate\ClaudecodeCli\Exception\ProcessTimedOut;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOut;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class ClaudeProcess
{
    /** Seconds a process asked to stop may take before it is killed. */
    private const STOP_GRACE = 5.0;

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

    /**
     * @param  (\Closure(): bool)|null  $closeInput  keeps stdin open after the
     *                                              input was written, until this
     *                                              first answers true. Without it
     *                                              stdin closes once written.
     * @param  (\Closure(): string)|null  $nextInput  asked for more input on every
     *                                              poll while stdin is open
     * @param  (\Closure(): bool)|null  $stop  ends the process once this answers
     *                                         true, without failing the run: the
     *                                         output read so far stands
     * @return \Generator<int, string, void, ProcessResult>
     */
    public function lines(?\Closure $isCancelled = null, ?\Closure $closeInput = null, ?\Closure $nextInput = null, ?\Closure $stop = null): \Generator
    {
        ClaudeExecutable::assertAvailable($this->command[0]);
        $stdin = $closeInput === null ? null : new InputStream;
        $process = new Process($this->command, $this->cwd, $this->env, $stdin ?? $this->input, $this->timeout);
        $reader = new StreamJsonReader;
        $stderr = '';

        try {
            $process->start();
            if ($stdin !== null && $this->input !== null && $this->input !== '') {
                $stdin->write($this->input);
            }
            do {
                if ($isCancelled !== null && $isCancelled()) {
                    $process->stop(0.2);
                    throw new ProcessCancelled('Claude Code run was cancelled.');
                }
                if ($stdin !== null && !$stdin->isClosed()) {
                    if ($nextInput !== null && ($more = $nextInput()) !== '') {
                        $stdin->write($more);
                    }
                    if ($closeInput()) {
                        $stdin->close();
                    }
                }
                if ($stop !== null && $stop()) {
                    // A process being stopped reads nothing more, and a reader
                    // blocked on stdin may not act on the signal until it ends.
                    $stdin?->close();
                    // SIGTERM lets Claude Code end the background commands it
                    // started; a kill would leave them running on their own.
                    $process->stop(self::STOP_GRACE);
                    break;
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
