<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\StreamItem;

interface Transport
{
    /** @return \Generator<int, StreamItem, void, ProcessResult> */
    public function stream(RunRequest $request): \Generator;
}
