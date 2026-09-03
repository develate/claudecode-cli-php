<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Exception;

final class InvalidStreamJson extends ClaudeException
{
    public function __construct(
        public readonly string $jsonLine,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'Claude Code emitted invalid stream JSON'.($previous === null ? '.' : ': '.$previous->getMessage()),
            0,
            $previous,
        );
    }
}
