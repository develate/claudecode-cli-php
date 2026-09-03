<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

enum PermissionMode: string
{
    case Default = 'default';
    case AcceptEdits = 'acceptEdits';
    case Plan = 'plan';
    case Auto = 'auto';
    case DontAsk = 'dontAsk';
    case BypassPermissions = 'bypassPermissions';
}
