<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\Parser\StreamJsonParser;
use Develate\ClaudecodeCli\Process\ProcessFactory;
use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\Value\PermissionMode;

final readonly class ExecTransport implements Transport
{
    public function __construct(
        private string $binary = 'claude',
        private ProcessFactory $processFactory = new ProcessFactory(),
        private StreamJsonParser $parser = new StreamJsonParser(),
    ) {
    }

    public function stream(RunRequest $request): \Generator
    {
        $process = $this->processFactory->create($this->command($request), $request->cwd, $request->timeout);
        $lines = $process->lines($request->isCancelled);
        foreach ($lines as $line) {
            foreach ($this->parser->parseLine($line) as $item) {
                yield $item;
            }
        }

        /** @var ProcessResult $result */
        $result = $lines->getReturn();

        return $result;
    }

    /** @return list<string> */
    public function command(RunRequest $request): array
    {
        $command = [
            $this->binary,
            '--print',
            '--output-format',
            'stream-json',
            '--verbose',
            '--include-partial-messages',
        ];

        if ($request->mode !== RunMode::Start) {
            if ($request->sessionId === null || $request->sessionId === '') {
                throw new \LogicException('Resume and fork runs require a session id.');
            }
            $command[] = '--resume';
            $command[] = $request->sessionId;
            if ($request->mode === RunMode::Fork) {
                $command[] = '--fork-session';
            }
        } elseif ($request->sessionId !== null && $request->sessionId !== '') {
            $command[] = '--session-id';
            $command[] = $request->sessionId;
        }

        if ($request->model !== null && $request->model !== '') {
            $command[] = '--model';
            $command[] = $request->model;
        }
        if ($request->permissionMode !== PermissionMode::Default) {
            $command[] = '--permission-mode';
            $command[] = $request->permissionMode->value;
        }
        foreach ($request->additionalDirectories as $directory) {
            $command[] = '--add-dir';
            $command[] = $directory;
        }
        if ($request->tools !== null) {
            $command[] = '--tools';
            $command[] = implode(',', $request->tools);
        }
        if ($request->allowedTools !== []) {
            $command[] = '--allowedTools';
            $command[] = implode(',', $request->allowedTools);
        }
        if ($request->disallowedTools !== []) {
            $command[] = '--disallowedTools';
            $command[] = implode(',', $request->disallowedTools);
        }
        if ($request->maxTurns !== null) {
            $command[] = '--max-turns';
            $command[] = (string) $request->maxTurns;
        }
        if ($request->maxBudgetUsd !== null) {
            $command[] = '--max-budget-usd';
            $command[] = self::decimal($request->maxBudgetUsd);
        }
        if ($request->schema !== null) {
            $command[] = '--json-schema';
            $command[] = json_encode($request->schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $command[] = $this->prompt($request);

        return $command;
    }

    private function prompt(RunRequest $request): string
    {
        if ($request->images === []) {
            return $request->prompt;
        }

        return $request->prompt."\n\nUse the following image files as visual inputs:\n- ".implode("\n- ", $request->images);
    }

    private static function decimal(float $number): string
    {
        return rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
    }
}
