<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\Exception\LimitResetFailed;
use Develate\ClaudecodeCli\LimitResetClient;
use Develate\ClaudecodeCli\Value\BankedResetResult;
use Develate\ClaudecodeCli\Value\BankedResetStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BankedResetTest extends TestCase
{
    public function test_banked_status_reads_the_cli_surface_without_spending_a_reset(): void
    {
        $calls = [];
        $client = new LimitResetClient('test-token', http: static function (string $method, string $url, array $headers, ?string $body) use (&$calls): array {
            $calls[] = [$method, $url, $headers, $body];

            return ['status' => 200, 'body' => json_encode(self::usage(), JSON_THROW_ON_ERROR)];
        }, cliVersion: '2.1.288');

        $status = $client->bankedStatus();

        self::assertCount(1, $calls);
        self::assertSame('GET', $calls[0][0]);
        self::assertSame('https://api.anthropic.com/api/oauth/usage?cedar_ember=1&skip_spend=1', $calls[0][1]);
        self::assertSame('claude-cli/2.1.288 (external, cli)', $calls[0][2]['User-Agent']);
        self::assertSame('Bearer test-token', $calls[0][2]['Authorization']);
        self::assertNull($calls[0][3]);
        self::assertSame(3, $status?->availableCount());
        self::assertSame('launch~2', $status?->grants[0]->creditId());
        self::assertSame('2026-10-22T12:00:00+00:00', $status?->summary()['credits'][0]['expires_at']);
        self::assertSame('available', $status?->summary()['credits'][0]['status']);
        self::assertSame('unavailable', $status?->summary()['credits'][1]['status']);
        self::assertSame(self::usage()['cedar_ember'], $status?->raw());
    }

    public function test_only_the_current_server_selected_usable_grant_can_be_redeemed(): void
    {
        $status = BankedResetStatus::fromUsage(self::usage());
        $now = new \DateTimeImmutable('2026-10-03T12:00:00Z');

        self::assertSame('launch', $status?->redeemableGrant('launch~2', $now)?->id);
        self::assertNull($status?->redeemableGrant('later~1', $now));
        self::assertNull($status?->redeemableGrant('launch~1', $now));
        self::assertNull($status?->redeemableGrant('launch~2', new \DateTimeImmutable('2026-10-22T12:00:00Z')));
    }

    #[DataProvider('blockedGrants')]
    public function test_unavailable_grants_are_not_redeemable(array $statusChanges, array $grantChanges): void
    {
        $usage = self::usage();
        $usage['cedar_ember'] = array_replace($usage['cedar_ember'], $statusChanges);
        $usage['cedar_ember']['grants'][0] = array_replace($usage['cedar_ember']['grants'][0], $grantChanges);
        $status = BankedResetStatus::fromUsage($usage);

        self::assertNull($status?->redeemableGrant('launch~2', new \DateTimeImmutable('2026-10-03T12:00:00Z')));
    }

    public static function blockedGrants(): array
    {
        return [
            'ineligible' => [['eligible' => false], []],
            'paused' => [[], ['paused' => true]],
            'not usable' => [[], ['usable_now' => false]],
            'starts later' => [[], ['starts_at' => '2026-10-04T12:00:00Z']],
            'cooldown' => [['cooldown_until' => '2026-10-03T12:01:00Z'], []],
            'invalid cooldown' => [['cooldown_until' => 'invalid'], []],
            'no selected grant' => [['next_grant_id' => null], []],
        ];
    }

    public function test_missing_availability_is_unknown_and_malformed_grants_are_dropped(): void
    {
        self::assertNull(BankedResetStatus::fromUsage([]));
        self::assertNull(BankedResetStatus::fromUsage(['cedar_ember' => ['eligible' => 'true']]));
        $usage = self::usage();
        $usage['cedar_ember']['grants'][] = ['id' => '../bad', 'resets_left' => 1];
        $usage['cedar_ember']['grants'][] = ['id' => 'bad', 'resets_left' => -1];

        self::assertCount(2, BankedResetStatus::fromUsage($usage)->grants);
    }

    public function test_consume_uses_grant_and_stable_request_ids_and_preserves_the_response(): void
    {
        $calls = [];
        $client = new LimitResetClient('test-token', http: static function (string $method, string $url, array $headers, ?string $body) use (&$calls): array {
            $calls[] = [$method, $url, $headers, json_decode($body, true)];

            return ['status' => 200, 'body' => '{"result":"reset","resets_left":1,"cleared":["five_hour","seven_day"],"future":true}'];
        });

        $result = $client->consumeBankedReset('org-123', 'launch', 'request-123');

        self::assertSame('POST', $calls[0][0]);
        self::assertSame('https://api.anthropic.com/api/organizations/org-123/reset_rate_limits', $calls[0][1]);
        self::assertSame(['program' => 'cedar_ember', 'grant_id' => 'launch', 'request_id' => 'request-123'], $calls[0][3]);
        self::assertTrue($result->didReset());
        self::assertSame(1, $result->resetsLeft);
        self::assertSame(['five_hour', 'seven_day'], $result->cleared);
        self::assertTrue($result->raw()['future']);
    }

    #[DataProvider('outcomes')]
    public function test_consume_outcomes_remain_distinct(string $reported, string $expected): void
    {
        self::assertSame($expected, BankedResetResult::fromArray(['result' => $reported])->outcome->value);
    }

    public static function outcomes(): array
    {
        return array_map(static fn (string $outcome): array => [$outcome, $outcome], [
            'reset', 'already_used', 'not_limited', 'cooldown', 'ineligible', 'unavailable',
        ]) + [6 => ['new_outcome', 'unavailable']];
    }

    #[DataProvider('invalidClaims')]
    public function test_invalid_claim_ids_are_rejected_before_sending(string $organization, string $grant, string $request): void
    {
        $client = new LimitResetClient('test-token', http: static function (): never {
            self::fail('An invalid claim must never be sent.');
        });

        $this->expectException(\InvalidArgumentException::class);
        $client->consumeBankedReset($organization, $grant, $request);
    }

    public static function invalidClaims(): array
    {
        return [
            'empty organization' => ['', 'launch', 'request'],
            'invalid grant' => ['org', 'bad/grant', 'request'],
            'empty request' => ['org', 'launch', ''],
            'long request' => ['org', 'launch', str_repeat('a', 65)],
        ];
    }

    public function test_rejected_banked_requests_do_not_expose_credentials(): void
    {
        $client = new LimitResetClient('private-token', http: static fn (): array => ['status' => 403, 'body' => '{}']);

        try {
            $client->bankedStatus();
            self::fail('Expected an authentication error.');
        } catch (LimitResetFailed $exception) {
            self::assertTrue($exception->isAuthenticationError());
            self::assertStringNotContainsString('private-token', $exception->getMessage());
        }
    }

    private static function usage(): array
    {
        return ['cedar_ember' => [
            'eligible' => true,
            'next_grant_id' => 'launch',
            'grants' => [
                ['id' => 'launch', 'resets_total' => 2, 'resets_left' => 2, 'ends_at' => '2026-10-22T14:00:00+02:00', 'usable_now' => true, 'use_requires_limit' => false],
                ['id' => 'later', 'resets_total' => 1, 'resets_left' => 1, 'ends_at' => '2026-11-01T00:00:00Z', 'usable_now' => true],
            ],
        ]];
    }
}
