<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * Reasoning effort for a session, mapped onto Claude Code's `--effort` flag.
 */
enum Effort: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case XHigh = 'xhigh';
    case Max = 'max';
}
