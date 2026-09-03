<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Exception\ClaudeNotFound;
use Develate\ClaudecodeCli\Exception\ProcessFailed;
use Develate\ClaudecodeCli\Process\ClaudeExecutable;
use Develate\ClaudecodeCli\Transport\ExecTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\Transport;
use Develate\ClaudecodeCli\Value\AuthStatus;
use Develate\ClaudecodeCli\Value\PermissionMode;
use Symfony\Component\Process\Exception\ProcessFailedException as SymfonyProcessFailed;
use Symfony\Component\Process\Exception\RuntimeException as SymfonyRuntimeException;
use Symfony\Component\Process\Process;

/**
 * The entry point: a Claude Code binary plus the environment it runs under.
 *
 * The environment is deliberately one setting for the whole object. `--version`,
 * `auth status` and every run have to observe the same `CLAUDE_CONFIG_DIR` and
 * the same absent API keys, or a host managing several accounts would report one
 * identity and run as another.
 */
final class Claude
{
    private readonly Transport $transport;

    private ?string $versionCache;

    private ?string $defaultCwd = null;

    private PermissionMode $defaultPermissionMode = PermissionMode::Default;

    /**
     * @param  array<string, string|false>  $env  merged onto the inherited environment;
     *                                            `false` removes an inherited variable
     */
    public function __construct(
        private readonly string $binary = 'claude',
        ?Transport $transport = null,
        private readonly ?float $timeout = null,
        ?string $version = null,
        private readonly array $env = [],
    ) {
        $this->transport = $transport ?? new ExecTransport($binary, $env);
        $this->versionCache = $version;
    }

    public function session(?SessionOptions $options = null): Session
    {
        return new Session(
            transport: $this->transport,
            claudeVersion: $this->version(),
            options: $this->resolveOptions($options),
            timeout: $this->timeout,
        );
    }

    public function resume(string $sessionId, ?SessionOptions $options = null): Session
    {
        if ($sessionId === '') {
            throw new \InvalidArgumentException('sessionId must not be empty.');
        }

        return new Session(
            transport: $this->transport,
            claudeVersion: $this->version(),
            options: $this->resolveOptions($options),
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

    public function query(string $prompt, ?RunOptions $options = null): Result
    {
        return $this->session()->query($prompt, $options);
    }

    public function version(): string
    {
        if ($this->versionCache !== null) {
            return $this->versionCache;
        }

        $output = trim($this->run(['--version'], 5.0));

        if (preg_match('/([0-9]+(?:\.[0-9]+){1,3}(?:[-+][^\s]+)?)/', $output, $matches) === 1) {
            return $this->versionCache = $matches[1];
        }

        return $this->versionCache = $output;
    }

    /**
     * The identity this binary and environment are signed in as.
     *
     * `claude auth status --json` exits non-zero when nobody is signed in, so a
     * failure that still produced a JSON body is read rather than thrown: "not
     * signed in" is an answer, not an error.
     */
    public function authStatus(?float $timeout = null): AuthStatus
    {
        try {
            $output = $this->run(['auth', 'status', '--json'], $timeout ?? 15.0);
        } catch (ProcessFailed $exception) {
            $decoded = self::decode($exception->getMessage());

            if ($decoded === null) {
                throw $exception;
            }

            return AuthStatus::fromArray($decoded);
        }

        $decoded = self::decode($output);

        if ($decoded === null) {
            throw new ProcessFailed(0, 'Claude Code did not report a JSON authentication status.');
        }

        return AuthStatus::fromArray($decoded);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, float $timeout): string
    {
        ClaudeExecutable::assertAvailable($this->binary);

        try {
            $process = new Process([$this->binary, ...$arguments], null, $this->env, null, $timeout);
            $process->mustRun();
        } catch (SymfonyProcessFailed $exception) {
            $failed = $exception->getProcess();
            $output = trim($failed->getOutput());

            throw new ProcessFailed(
                $failed->getExitCode() ?? 1,
                $output !== '' ? $output : $failed->getErrorOutput(),
            );
        } catch (SymfonyRuntimeException $exception) {
            throw new ClaudeNotFound(sprintf('Claude Code binary "%s" was not found.', $this->binary), 0, $exception);
        }

        return $process->getOutput();
    }

    private function resolveOptions(?SessionOptions $options): SessionOptions
    {
        if ($options === null) {
            return new SessionOptions(
                cwd: $this->resolveCwd(null),
                permissionMode: $this->defaultPermissionMode,
            );
        }

        return $this->defaultPermissionMode === PermissionMode::BypassPermissions
            && $options->permissionMode === PermissionMode::Default
            ? $options->withPermissionMode(PermissionMode::BypassPermissions)
            : $options;
    }

    private function resolveCwd(?string $cwd): string
    {
        $resolved = $cwd ?? $this->defaultCwd ?? getcwd();
        if (! is_string($resolved) || $resolved === '') {
            throw new \RuntimeException('Unable to determine the working directory.');
        }

        return $resolved;
    }

    /** @return array<string, mixed>|null */
    private static function decode(string $output): ?array
    {
        $start = strpos($output, '{');

        if ($start === false) {
            return null;
        }

        $decoded = json_decode(substr($output, $start), true);

        return is_array($decoded) ? $decoded : null;
    }
}
