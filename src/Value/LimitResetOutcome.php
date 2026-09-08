<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * How a session-limit reset claim turned out.
 *
 * Mirrors the `result` values of
 * `POST /api/organizations/{uuid}/reset_rate_limits`. Unknown values map to
 * `Unavailable`, the same fallback Claude Code itself uses.
 */
enum LimitResetOutcome: string
{
    case Reset = 'reset';
    case AlreadyUsed = 'already_used';
    case NotLimited = 'not_limited';
    case Ineligible = 'ineligible';
    case Unavailable = 'unavailable';

    public static function fromString(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unavailable;
        }

        return self::tryFrom(strtolower(trim($value))) ?? self::Unavailable;
    }
}
