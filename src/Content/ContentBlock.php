<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Content;

interface ContentBlock
{
    /** @return array<string, mixed> */
    public function raw(): array;
}
