<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Support;

use Develate\ClaudecodeCli\Exception\ProcessCancelled;
use Develate\ClaudecodeCli\Parser\StreamJsonParser;
use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Transport\Transport;

/**
 * Replays raw stream-json lines through the real parser.
 *
 * Closer to production than handing the run finished objects: everything a real
 * Claude Code process would emit still has to survive parsing.
 */
final class ScriptedTransport implements Transport
{
    /** @var list<RunRequest> */
    public array $requests = [];

    /** @param list<array<string, mixed>|string> $lines */
    public function __construct(
        private readonly array $lines,
        private readonly int $exitCode = 0,
        private readonly string $stderr = '',
    ) {}

    public function stream(RunRequest $request): \Generator
    {
        $this->requests[] = $request;
        $parser = new StreamJsonParser;

        foreach ($this->lines as $line) {
            // A cancelled process dies rather than finishing tidily, so the
            // fake raises the same exception the real transport does.
            if ($request->isCancelled !== null && ($request->isCancelled)()) {
                throw new ProcessCancelled('Claude Code run was cancelled.');
            }

            $json = is_string($line) ? $line : json_encode($line, JSON_THROW_ON_ERROR);

            foreach ($parser->parseLine($json) as $item) {
                yield $item;
            }
        }

        return new ProcessResult($this->exitCode, $this->stderr);
    }
}
