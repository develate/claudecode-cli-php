<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Exception\ClaudeNotFound;
use Develate\ClaudecodeCli\Exception\ProcessFailed;
use Develate\ClaudecodeCli\Process\ClaudeExecutable;
use Develate\ClaudecodeCli\Transport\ExecTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\Transport;
use Develate\ClaudecodeCli\Value\PermissionMode;
use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailed;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use Symfony\Component\Process\Process;

final class Claude
{
    private readonly Transport $transport;
    private ?string $versionCache;
    private ?string $defaultCwd = null;
    private PermissionMode $defaultPermissionMode = PermissionMode::Default;

    public function __construct(
        private readonly string $binary = 'claude',
        ?Transport $transport = null,
        private readonly ?float $timeout = null,
        ?string $version = null,
    ) {
        $this->transport = $transport ?? new ExecTransport($binary);
        $this->versionCache = $version;
    }

    /**
     * @param list<string> $additionalDirectories
     * @param list<string>|null $tools
     * @param list<string> $allowedTools
     * @param list<string> $disallowedTools
     */
    public function session(
        ?string $cwd = null,
        ?string $model = null,
        ?PermissionMode $permissionMode = null,
        array $additionalDirectories = [],
        ?array $tools = null,
        array $allowedTools = [],
        array $disallowedTools = [],
        ?string $sessionId = null,
    ): Session {
        return new Session(
            transport: $this->transport,
            claudeVersion: $this->version(),
            cwd: $this->resolveCwd($cwd),
            model: $model,
            permissionMode: $permissionMode ?? $this->defaultPermissionMode,
            additionalDirectories: $additionalDirectories,
            tools: $tools,
            allowedTools: $allowedTools,
            disallowedTools: $disallowedTools,
            timeout: $this->timeout,
            sessionId: $sessionId,
        );
    }

    /**
     * @param list<string> $additionalDirectories
     * @param list<string>|null $tools
     * @param list<string> $allowedTools
     * @param list<string> $disallowedTools
     */
    public function resume(
        string $sessionId,
        ?string $cwd = null,
        ?string $model = null,
        ?PermissionMode $permissionMode = null,
        array $additionalDirectories = [],
        ?array $tools = null,
        array $allowedTools = [],
        array $disallowedTools = [],
    ): Session {
        if ($sessionId === '') {
            throw new \InvalidArgumentException('sessionId must not be empty.');
        }

        return new Session(
            transport: $this->transport,
            claudeVersion: $this->version(),
            cwd: $this->resolveCwd($cwd),
            model: $model,
            permissionMode: $permissionMode ?? $this->defaultPermissionMode,
            additionalDirectories: $additionalDirectories,
            tools: $tools,
            allowedTools: $allowedTools,
            disallowedTools: $disallowedTools,
            timeout: $this->timeout,
            sessionId: $sessionId,
            nextMode: RunMode::Resume,
        );
    }

    public function in(string $cwd): self
    {
        $clone = clone $this;
        $clone->defaultCwd = $cwd;

        return $clone;
    }

    public function dangerouslyBypassPermissions(): self
    {
        $clone = clone $this;
        $clone->defaultPermissionMode = PermissionMode::BypassPermissions;

        return $clone;
    }

    /** @param array<string, mixed>|null $schema @param list<string> $images */
    public function query(string $prompt, ?array $schema = null, array $images = []): Result
    {
        return $this->session()->query($prompt, schema: $schema, images: $images);
    }

    public function version(): string
    {
        if ($this->versionCache !== null) {
            return $this->versionCache;
        }

        ClaudeExecutable::assertAvailable($this->binary);
        try {
            $process = new Process([$this->binary, '--version'], timeout: 5.0);
            $process->mustRun();
        } catch (SymfonyProcessFailed $exception) {
            throw new ProcessFailed($exception->getProcess()->getExitCode() ?? 1, $exception->getProcess()->getErrorOutput());
        } catch (SymfonyRuntimeException $exception) {
            throw new ClaudeNotFound(sprintf('Claude Code binary "%s" was not found.', $this->binary), 0, $exception);
        }

        $output = trim($process->getOutput());
        if (preg_match('/([0-9]+(?:\.[0-9]+){1,3}(?:[-+][^\s]+)?)/', $output, $matches) === 1) {
            return $this->versionCache = $matches[1];
        }

        return $this->versionCache = $output;
    }

    private function resolveCwd(?string $cwd): string
    {
        $resolved = $cwd ?? $this->defaultCwd ?? getcwd();
        if (!is_string($resolved) || $resolved === '') {
            throw new \RuntimeException('Unable to determine the working directory.');
        }

        return $resolved;
    }
}
