<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

use Develate\ClaudecodeCli\Exception\ClaudeNotFound;
use Symfony\Component\Process\ExecutableFinder;

final class ClaudeExecutable
{
    public static function assertAvailable(string $binary): void
    {
        $hasPath = str_contains($binary, '/') || str_contains($binary, '\\');
        $available = $hasPath
            ? is_file($binary) && is_executable($binary)
            : (new ExecutableFinder())->find($binary) !== null;

        if (!$available) {
            throw new ClaudeNotFound(sprintf('Claude Code binary "%s" was not found or is not executable.', $binary));
        }
    }
}
