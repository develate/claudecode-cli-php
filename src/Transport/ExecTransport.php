<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\Parser\StreamJsonParser;
use Develate\ClaudecodeCli\Process\ProcessFactory;
use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\SessionOptions;
use Develate\ClaudecodeCli\Value\PermissionMode;
use Develate\ClaudecodeCli\Value\SettingSource;

/**
 * One `claude -p --output-format stream-json` process per run.
 *
 * The prompt travels on stdin rather than as a trailing argument: several of
 * Claude Code's flags are variadic, and a positional prompt sitting behind one
 * of them is swallowed as another value. Stdin also removes the operating
 * system's argument-length ceiling, which a long transcript reaches easily.
 */
final readonly class ExecTransport implements Transport
{
    /**
     * @param  array<string, string|false>  $env  process environment applied to every run
     */
    public function __construct(
        private string $binary = 'claude',
        private array $env = [],
        private ProcessFactory $processFactory = new ProcessFactory,
        private StreamJsonParser $parser = new StreamJsonParser,
    ) {}

    public function stream(RunRequest $request): \Generator
    {
        $process = $this->processFactory->create(
            $this->command($request),
            $request->cwd(),
            $request->timeout(),
            $this->env,
            $this->input($request),
        );

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
        $session = $request->session;

        $command = [$this->binary, '--print', '--output-format', 'stream-json', '--verbose'];

        if ($session->includePartialMessages) {
            $command[] = '--include-partial-messages';
        }

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

        array_push($command, ...$this->modelFlags($session));
        array_push($command, ...$this->permissionFlags($session));
        array_push($command, ...$this->toolFlags($session));
        array_push($command, ...$this->configurationFlags($session));
        array_push($command, ...$this->systemPromptFlags($session));
        array_push($command, ...$this->limitFlags($request));

        return $command;
    }

    /**
     * The prompt, as it is written to the child process' stdin.
     */
    public function input(RunRequest $request): string
    {
        if ($request->run->images === []) {
            return $request->prompt;
        }

        return $request->prompt
            ."\n\nUse the following image files as visual inputs:\n- "
            .implode("\n- ", $request->run->images);
    }

    /** @return list<string> */
    private function modelFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->model !== null && $session->model !== '') {
            $flags[] = '--model';
            $flags[] = $session->model;
        }
        if ($session->effort !== null) {
            $flags[] = '--effort';
            $flags[] = $session->effort->value;
        }

        return $flags;
    }

    /** @return list<string> */
    private function permissionFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->permissionMode !== PermissionMode::Default) {
            $flags[] = '--permission-mode';
            $flags[] = $session->permissionMode->value;
        }
        if ($session->permissionPromptTool !== null && $session->permissionPromptTool !== '') {
            $flags[] = '--permission-prompt-tool';
            $flags[] = $session->permissionPromptTool;
        }
        if ($session->permissionPrompts !== null) {
            $flags[] = '--permission-prompts';
            $flags[] = $session->permissionPrompts->value;
        }

        return $flags;
    }

    /** @return list<string> */
    private function toolFlags(SessionOptions $session): array
    {
        $flags = [];

        foreach ($session->additionalDirectories as $directory) {
            $flags[] = '--add-dir';
            $flags[] = $directory;
        }
        if ($session->tools !== null) {
            $flags[] = '--tools';
            $flags[] = implode(',', $session->tools);
        }
        if ($session->allowedTools !== []) {
            $flags[] = '--allowedTools';
            $flags[] = implode(',', $session->allowedTools);
        }
        if ($session->disallowedTools !== []) {
            $flags[] = '--disallowedTools';
            $flags[] = implode(',', $session->disallowedTools);
        }

        return $flags;
    }

    /**
     * Settings, MCP and the Chrome integration.
     *
     * An empty `settingSources` is sent rather than omitted: it is the only way
     * to tell Claude Code to load none of the user, project and local settings
     * files, and dropping it would silently run whatever the project ships.
     *
     * @return list<string>
     */
    private function configurationFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->settingSources !== null) {
            $flags[] = '--setting-sources';
            $flags[] = implode(',', array_map(
                static fn (SettingSource $source): string => $source->value,
                $session->settingSources,
            ));
        }
        if ($session->settings !== null && $session->settings !== '') {
            $flags[] = '--settings';
            $flags[] = $session->settings;
        }
        foreach ($session->mcpConfig as $config) {
            $flags[] = '--mcp-config';
            $flags[] = $config;
        }
        if ($session->strictMcpConfig) {
            $flags[] = '--strict-mcp-config';
        }
        if ($session->chrome !== null) {
            $flags[] = $session->chrome ? '--chrome' : '--no-chrome';
        }
        if ($session->disableSlashCommands) {
            $flags[] = '--disable-slash-commands';
        }
        if ($session->forwardSubagentText) {
            $flags[] = '--forward-subagent-text';
        }

        return $flags;
    }

    /** @return list<string> */
    private function systemPromptFlags(SessionOptions $session): array
    {
        $flags = [];

        if ($session->systemPrompt !== null) {
            $flags[] = '--system-prompt';
            $flags[] = $session->systemPrompt;
        }
        if ($session->appendSystemPrompt !== null && $session->appendSystemPrompt !== '') {
            $flags[] = '--append-system-prompt';
            $flags[] = $session->appendSystemPrompt;
        }

        return $flags;
    }

    /** @return list<string> */
    private function limitFlags(RunRequest $request): array
    {
        $run = $request->run;
        $flags = [];

        if ($run->maxTurns !== null) {
            $flags[] = '--max-turns';
            $flags[] = (string) $run->maxTurns;
        }
        if ($run->maxBudgetUsd !== null) {
            $flags[] = '--max-budget-usd';
            $flags[] = self::decimal($run->maxBudgetUsd);
        }
        if ($run->schema !== null) {
            $flags[] = '--json-schema';
            $flags[] = json_encode($run->schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        return $flags;
    }

    private static function decimal(float $number): string
    {
        return rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
    }
}
