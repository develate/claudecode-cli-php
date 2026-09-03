<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

use Develate\ClaudecodeCli\Value\PermissionDenial;

final readonly class PermissionDeniedEvent implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(public PermissionDenial $denial, private array $raw)
    {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
