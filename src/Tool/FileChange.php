<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tool;

use Develate\ClaudecodeCli\Value\FileChangeKind;

final readonly class FileChange
{
    public function __construct(
        public string $toolUseId,
        public string $path,
        public FileChangeKind $kind,
    ) {
    }
}
