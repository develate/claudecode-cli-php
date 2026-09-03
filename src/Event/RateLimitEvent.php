<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Event;

use Develate\ClaudecodeCli\Value\RateLimitInfo;

final readonly class RateLimitEvent implements Event
{
    /** @param array<string, mixed> $raw */
    public function __construct(public RateLimitInfo $rateLimit, private array $raw)
    {
    }

    public function raw(): array
    {
        return $this->raw;
    }
}
