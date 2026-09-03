<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tool;

final readonly class Command
{
    public function __construct(
        public string $toolUseId,
        public string $command,
        public ?string $output,
        public ?int $exitCode,
    ) {
    }
}
