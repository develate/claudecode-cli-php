<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * The experiment arm behind a session-limit reset offer, if any.
 */
enum LimitResetArm: string
{
    case Control = 'control';
    case Reset = 'reset';
}
