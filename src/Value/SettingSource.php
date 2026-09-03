<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * A settings file location Claude Code may load.
 *
 * An empty list is meaningful: it is sent as an empty `--setting-sources` and
 * loads none of them, which is how a host keeps project configuration out of a
 * run it controls.
 */
enum SettingSource: string
{
    case User = 'user';
    case Project = 'project';
    case Local = 'local';
}
