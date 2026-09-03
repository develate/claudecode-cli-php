<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

enum FileChangeKind: string
{
    case Edit = 'edit';
    case Write = 'write';
}
