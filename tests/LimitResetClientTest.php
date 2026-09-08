<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\Exception\LimitResetFailed;
use Develate\ClaudecodeCli\LimitResetClient;
use Develate\ClaudecodeCli\Value\LimitResetIneligibility;
use Develate\ClaudecodeCli\Value\LimitResetOutcome;
use PHPUnit\Framework\TestCase;

final class LimitResetClientTest extends TestCase
{
    public function testStatusUsesTheAtWallUsageEndpoint(): void
    {
        $calls = [];
        $client = new LimitResetClient('token-123', 'https://api.example.test/', http: function (
            string $method,
            string $url,
            array $headers,
            ?string $body,
        ) use (&$calls): array {
            $calls[] = [$method, $url, $headers, $body];

            return ['status' => 200, 'body' => json_encode([
                'five_hour' => ['utilization' => 90],
                'juniper_tide' => [
                    'eligible' => true,
                    'ineligible_reason' => null,
                    'available' => true,
                    'resets_per_week' => 1,
                ],
            ], JSON_THROW_ON_ERROR)];
        });

        $status = $client->status();

        self::assertCount(1, $calls);
        [$method, $url, $headers, $body] = $calls[0];
        self::assertSame('GET', $method);
        self::assertSame('https://api.example.test/api/oauth/usage?at_wall=1&skip_spend=1', $url);
        self::assertSame('Bearer token-123', $headers['Authorization']);
        self::assertSame('oauth-2025-04-20', $headers['anthropic-beta']);
        self::assertNull($body);
        self::assertTrue($status?->redeemable());
        self::assertNull($status?->ineligibleReason);
    }

    public function testStatusReturnsNullWhenTheApiReportsNoBlock(): void
    {
        $client = new LimitResetClient('token-123', http: static fn (): array => [
            'status' => 200,
            'body' => '{"five_hour":{"utilization":10},"juniper_tide":null}',
        ]);

        self::assertNull($client->status());
    }

    public function testStatusTreatsAnUnauthorizedTokenAsAnAuthenticationError(): void
    {
        $client = new LimitResetClient('stale-token', http: static fn (): array => [
            'status' => 401,
            'body' => '{"error":"invalid_token"}',
        ]);

        try {
            $client->status();
            self::fail('Expected a LimitResetFailed.');
        } catch (LimitResetFailed $exception) {
            self::assertTrue($exception->isAuthenticationError());
            self::assertSame(401, $exception->statusCode());
            self::assertStringNotContainsString('stale-token', $exception->getMessage());
        }
    }

    public function testStatusRejectsServerErrorsAndInvalidBodies(): void
    {
        $failing = new LimitResetClient('token-123', http: static fn (): array => ['status' => 500, 'body' => 'oops']);

        try {
            $failing->status();
            self::fail('Expected a LimitResetFailed.');
        } catch (LimitResetFailed $exception) {
            self::assertFalse($exception->isAuthenticationError());
            self::assertSame(500, $exception->statusCode());
        }

        $invalid = new LimitResetClient('token-123', http: static fn (): array => ['status' => 200, 'body' => 'not json']);

        $this->expectException(LimitResetFailed::class);
        $invalid->status();
    }

    public function testResetClaimsTheWeeklyResetForTheOrganization(): void
    {
        $calls = [];
        $client = new LimitResetClient('token-123', http: function (
            string $method,
            string $url,
            array $headers,
            ?string $body,
        ) use (&$calls): array {
            $calls[] = [$method, $url, $headers, $body];

            return ['status' => 200, 'body' => json_encode([
                'result' => 'reset',
                'next_available_at' => '2026-09-14T06:00:00+00:00',
                'weekly_resets_at' => '2026-09-14T06:00:00+00:00',
            ], JSON_THROW_ON_ERROR)];
        });

        $result = $client->reset('128e7a9e-a925-416d-aef9-b2fffbe00334');

        self::assertCount(1, $calls);
        [$method, $url, $headers, $body] = $calls[0];
        self::assertSame('POST', $method);
        self::assertSame(
            'https://api.anthropic.com/api/organizations/128e7a9e-a925-416d-aef9-b2fffbe00334/reset_rate_limits',
            $url,
        );
        self::assertSame('Bearer token-123', $headers['Authorization']);
        self::assertSame(['program' => LimitResetClient::PROGRAM], json_decode((string) $body, true));
        self::assertSame(LimitResetOutcome::Reset, $result->outcome);
        self::assertTrue($result->didReset());
        self::assertFalse($result->spent());
        self::assertSame('2026-09-14', $result->nextAvailableAt?->format('Y-m-d'));
    }

    public function testResetReportsAnAlreadySpentWeek(): void
    {
        $client = new LimitResetClient('token-123', http: static fn (): array => [
            'status' => 200,
            'body' => '{"result":"already_used","next_available_at":"2026-09-14T06:00:00+00:00"}',
        ]);

        $result = $client->reset('org-uuid');

        self::assertSame(LimitResetOutcome::AlreadyUsed, $result->outcome);
        self::assertTrue($result->spent());
        self::assertFalse($result->didReset());
    }

    public function testOrganizationUuidReadsTheProfile(): void
    {
        $calls = [];
        $client = new LimitResetClient('token-123', http: function (
            string $method,
            string $url,
        ) use (&$calls): array {
            $calls[] = [$method, $url];

            return ['status' => 200, 'body' => '{"organization":{"uuid":"org-uuid-1"}}'];
        });

        self::assertSame('org-uuid-1', $client->organizationUuid());
        self::assertSame([['GET', 'https://api.anthropic.com/api/oauth/profile']], $calls);

        $missing = new LimitResetClient('token-123', http: static fn (): array => ['status' => 200, 'body' => '{}']);

        self::assertNull($missing->organizationUuid());
    }

    public function testResetRequiresCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LimitResetClient('');
    }

    public function testResetRequiresAnOrganization(): void
    {
        $client = new LimitResetClient('token-123', http: static fn (): array => ['status' => 200, 'body' => '{}']);

        $this->expectException(\InvalidArgumentException::class);
        $client->reset('');
    }

    public function testIneligibilityRoundTripsEveryDocumentedReason(): void
    {
        foreach (['tier', 'tenure', 'surface', 'mobile', 'cli_version', 'not_at_wall', 'weekly_limit', 'no_weekly_limit', 'other_experiment', 'extra_usage', 'unavailable', 'unknown'] as $reason) {
            self::assertSame($reason, LimitResetIneligibility::fromString($reason)->value);
        }

        self::assertSame(LimitResetIneligibility::Unknown, LimitResetIneligibility::fromString('something-new'));
    }
}
