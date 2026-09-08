<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * Why Claude Code reports a session-limit reset as not eligible.
 *
 * Mirrors the `ineligible_reason` values of the `juniper_tide` block in
 * `GET /api/oauth/usage?at_wall=1&skip_spend=1`. Unknown values map to
 * `Unknown` so a new reason never breaks parsing.
 */
enum LimitResetIneligibility: string
{
    case Tier = 'tier';
    case Tenure = 'tenure';
    case Surface = 'surface';
    case Mobile = 'mobile';
    case CliVersion = 'cli_version';
    case NotAtWall = 'not_at_wall';
    case WeeklyLimit = 'weekly_limit';
    case NoWeeklyLimit = 'no_weekly_limit';
    case OtherExperiment = 'other_experiment';
    case ExtraUsage = 'extra_usage';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';

    public static function fromString(mixed $value): self
    {
        if (! is_string($value)) {
            return self::Unknown;
        }

        return self::tryFrom(strtolower(trim($value))) ?? self::Unknown;
    }
}
