<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * Who answers permission prompts in `--print` mode.
 *
 * `Host` hands them to the SDK host or to `--permission-prompt-tool`; `None`
 * denies anything that would prompt without asking anybody.
 */
enum PermissionPrompts: string
{
    case Host = 'host';
    case None = 'none';
}
