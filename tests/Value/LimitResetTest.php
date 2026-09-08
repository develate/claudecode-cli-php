<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Value;

use Develate\ClaudecodeCli\Value\LimitResetArm;
use Develate\ClaudecodeCli\Value\LimitResetIneligibility;
use Develate\ClaudecodeCli\Value\LimitResetOutcome;
use Develate\ClaudecodeCli\Value\LimitResetResult;
use Develate\ClaudecodeCli\Value\LimitResetStatus;
use PHPUnit\Framework\TestCase;

final class LimitResetTest extends TestCase
{
    public function testStatusParsesTheLiveAvailabilityBlock(): void
    {
        // Shape observed from GET /api/oauth/usage?at_wall=1&skip_spend=1.
        $status = LimitResetStatus::fromArray([
            'eligible' => false,
            'ineligible_reason' => 'surface',
            'in_experiment' => false,
            'arm' => null,
            'available' => false,
            'next_available_at' => null,
            'weekly_resets_at' => null,
            'resets_per_week' => 1,
            'event_props' => null,
        ]);

        self::assertFalse($status->eligible);
        self::assertSame(LimitResetIneligibility::Surface, $status->ineligibleReason);
        self::assertFalse($status->inExperiment);
        self::assertNull($status->arm);
        self::assertFalse($status->available);
        self::assertNull($status->nextAvailableAt);
        self::assertNull($status->weeklyResetsAt);
        self::assertSame(1, $status->resetsPerWeek);
        self::assertSame([], $status->eventProps);
        self::assertFalse($status->redeemable());
    }

    public function testRedeemableNeedsEligibilityAndAvailability(): void
    {
        $offered = LimitResetStatus::fromArray([
            'eligible' => true,
            'available' => true,
            'arm' => 'reset',
            'next_available_at' => '2026-09-14T06:00:00.316390+00:00',
            'weekly_resets_at' => '2026-09-14T06:00:00.316390+00:00',
            'resets_per_week' => 1,
        ]);

        self::assertTrue($offered->redeemable());
        self::assertSame(LimitResetArm::Reset, $offered->arm);
        self::assertSame('2026-09-14', $offered->nextAvailableAt?->format('Y-m-d'));
        self::assertSame('2026-09-14', $offered->weeklyResetsAt?->format('Y-m-d'));

        $spent = LimitResetStatus::fromArray(['eligible' => true, 'available' => false]);

        self::assertFalse($spent->redeemable());

        $ineligible = LimitResetStatus::fromArray(['eligible' => false, 'available' => true]);

        self::assertFalse($ineligible->redeemable());
    }

    public function testStatusToleratesUnknownReasonsAndEpochDates(): void
    {
        $status = LimitResetStatus::fromArray([
            'eligible' => true,
            'ineligible_reason' => 'something-new',
            'available' => true,
            'next_available_at' => 1789375200,
            'weekly_resets_at' => 1789375200000,
            'resets_per_week' => 'weekly',
        ]);

        self::assertSame(LimitResetIneligibility::Unknown, $status->ineligibleReason);
        self::assertSame(1789375200, $status->nextAvailableAt?->getTimestamp());
        self::assertSame(1789375200, $status->weeklyResetsAt?->getTimestamp());
        self::assertSame(1, $status->resetsPerWeek);
    }

    public function testResultParsesEveryDocumentedOutcome(): void
    {
        foreach (LimitResetOutcome::cases() as $outcome) {
            $result = LimitResetResult::fromArray([
                'result' => $outcome->value,
                'next_available_at' => null,
                'weekly_resets_at' => null,
            ]);

            self::assertSame($outcome, $result->outcome);
            self::assertSame($outcome === LimitResetOutcome::Reset, $result->didReset());
            self::assertSame($outcome === LimitResetOutcome::AlreadyUsed, $result->spent());
        }
    }

    public function testResultFallsBackToUnavailable(): void
    {
        $result = LimitResetResult::fromArray(['result' => 'something-new']);

        self::assertSame(LimitResetOutcome::Unavailable, $result->outcome);
        self::assertFalse($result->didReset());
        self::assertFalse($result->spent());
    }
}
