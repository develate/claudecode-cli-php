<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

enum RunMode
{
    case Start;
    case Resume;
    case Fork;
}
