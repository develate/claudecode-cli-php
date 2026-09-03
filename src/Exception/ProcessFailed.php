<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Exception;

final class ProcessFailed extends ClaudeException
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stderr = '',
    ) {
        parent::__construct(sprintf(
            'Claude Code exited with code %d%s',
            $exitCode,
            $stderr === '' ? '.' : ': '.trim($stderr),
        ), $exitCode);
    }
}
