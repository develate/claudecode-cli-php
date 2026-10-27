<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

enum BankedResetOutcome: string
{
    case Reset = 'reset';
    case AlreadyUsed = 'already_used';
    case NotLimited = 'not_limited';
    case Cooldown = 'cooldown';
    case Ineligible = 'ineligible';
    case Unavailable = 'unavailable';

    public static function fromString(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Unavailable) : self::Unavailable;
    }
}
