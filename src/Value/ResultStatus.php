<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

enum ResultStatus: string
{
    case Success = 'success';
    case MaxTurns = 'max_turns';
    case MaxBudget = 'max_budget';
    case StructuredOutputRetries = 'structured_output_retries';
    case Error = 'error';
    case Unknown = 'unknown';

    public static function fromResult(string $subtype, bool $isError, ?string $terminalReason): self
    {
        if ($terminalReason === 'max_turns') {
            return self::MaxTurns;
        }
        if (in_array($terminalReason, ['budget_exhausted', 'max_budget_usd'], true)) {
            return self::MaxBudget;
        }

        return match ($subtype) {
            'success' => $isError ? self::Error : self::Success,
            'error_max_turns' => self::MaxTurns,
            'error_max_budget_usd' => self::MaxBudget,
            'error_max_structured_output_retries' => self::StructuredOutputRetries,
            'error_during_execution' => self::Error,
            default => match ($terminalReason) {
                'completed' => self::Success,
                default => $isError ? self::Error : self::Unknown,
            },
        };
    }
}
