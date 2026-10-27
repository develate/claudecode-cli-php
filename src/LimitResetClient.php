<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Exception\LimitResetFailed;
use Develate\ClaudecodeCli\Value\BankedResetResult;
use Develate\ClaudecodeCli\Value\BankedResetStatus;
use Develate\ClaudecodeCli\Value\LimitResetResult;
use Develate\ClaudecodeCli\Value\LimitResetStatus;

/**
 * Availability and redemption for Claude Code's session-limit and banked resets.
 *
 * The interactive command itself cannot run headless — Claude Code marks it
 * `supportsNonInteractive: false` — but both of its behaviors are plain
 * OAuth API calls under the account's own token, so a host can offer the
 * same feature without a TTY:
 *
 * - `status()` reads the `juniper_tide` block of
 *   `GET /api/oauth/usage?at_wall=1&skip_spend=1`. A reset of the session
 *   (5-hour) limit still counts toward the weekly limit and can be claimed
 *   at most once per week.
 * - `reset()` claims it via
 *   `POST /api/organizations/{uuid}/reset_rate_limits`.
 * - `bankedStatus()` reads the independent `cedar_ember` program, and
 *   `consumeBankedReset()` spends one reset from its server-selected grant.
 *
 * The organization UUID comes from `GET /api/oauth/profile`
 * (`organization.uuid`). A 401/403 throws a `LimitResetFailed` whose
 * `isAuthenticationError()` is true: the token is stale or misses the
 * profile scope, and retrying with the same token cannot succeed.
 */
final class LimitResetClient
{
    public const PROGRAM = 'juniper_tide';

    public const BANKED_PROGRAM = 'cedar_ember';

    public const BANKED_USAGE_PATH = '/api/oauth/usage?cedar_ember=1&skip_spend=1';

    private const OAUTH_BETA = 'oauth-2025-04-20';

    private const USER_AGENT = 'develate-claudecode-cli-php';

    private readonly string $baseUrl;

    /** @var callable(string, string, array<string, string>, string|null): (array{status: int, body: string}|null) */
    private $http;

    /**
     * @param  (callable(string, string, array<string, string>, string|null): (array{status: int, body: string}|null))|null  $http
     *                                                                                                                              `fn(method, url, headers, body)`; defaults to a plain PHP
     *                                                                                                                              streams sender so the package needs no HTTP dependency.
     */
    public function __construct(
        private readonly string $token,
        string $baseUrl = 'https://api.anthropic.com',
        private readonly ?float $timeout = 10.0,
        ?callable $http = null,
        private readonly ?string $cliVersion = null,
    ) {
        if (trim($this->token) === '') {
            throw new \InvalidArgumentException('token must not be empty.');
        }

        $normalized = rtrim(trim($baseUrl), '/');

        if ($normalized === '') {
            throw new \InvalidArgumentException('baseUrl must not be empty.');
        }

        $this->baseUrl = $normalized;
        $this->http = $http ?? $this->defaultSender();
    }

    /**
     * The current reset availability, or null when the API reports no
     * `juniper_tide` block. Null is "unknown", not "ineligible": the plain
     * usage endpoint answers `"juniper_tide": null`, so only the `at_wall`
     * variant used here is a real signal.
     */
    public function status(): ?LimitResetStatus
    {
        $response = ($this->http)('GET', $this->baseUrl.'/api/oauth/usage?at_wall=1&skip_spend=1', $this->headers(), null);
        $usage = $this->payload('GET', '/api/oauth/usage', $response);

        $block = $usage['juniper_tide'] ?? null;

        if ($block === null) {
            return null;
        }

        if (! is_array($block)) {
            throw new LimitResetFailed('Anthropic reported an invalid session-limit reset status.');
        }

        return LimitResetStatus::fromArray($block);
    }

    public function bankedStatus(): ?BankedResetStatus
    {
        $response = ($this->http)('GET', $this->baseUrl.self::BANKED_USAGE_PATH, $this->bankedHeaders(), null);

        return BankedResetStatus::fromUsage($this->payload('GET', self::BANKED_USAGE_PATH, $response));
    }

    /** Spend one reset from a grant, reusing requestId on every retry. */
    public function consumeBankedReset(string $organizationUuid, string $grantId, string $requestId): BankedResetResult
    {
        if (trim($organizationUuid) === '') {
            throw new \InvalidArgumentException('organizationUuid must not be empty.');
        }

        if (preg_match('/\A[a-z0-9_-]{1,40}\z/', $grantId) !== 1
            || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $requestId) !== 1) {
            throw new \InvalidArgumentException('Invalid banked reset grant or request ID.');
        }

        $path = '/api/organizations/'.rawurlencode(trim($organizationUuid)).'/reset_rate_limits';
        $response = ($this->http)('POST', $this->baseUrl.$path, $this->bankedHeaders(), json_encode([
            'program' => self::BANKED_PROGRAM,
            'grant_id' => $grantId,
            'request_id' => $requestId,
        ], JSON_THROW_ON_ERROR));

        return BankedResetResult::fromArray($this->payload('POST', $path, $response));
    }

    /** Anthropic uses the CLI surface and version to determine grant eligibility. */
    public static function bankedUserAgent(?string $version = null): string
    {
        return 'claude-cli/'.($version ?? '2.1.288').' (external, cli)';
    }

    /** @return array<string, string> */
    private function bankedHeaders(): array
    {
        return array_replace($this->headers(), ['User-Agent' => self::bankedUserAgent($this->cliVersion)]);
    }

    /**
     * The OAuth organization UUID a reset claim is billed against
     * (`organization.uuid` from the OAuth profile), or null when the
     * profile does not report one.
     */
    public function organizationUuid(): ?string
    {
        $response = ($this->http)('GET', $this->baseUrl.'/api/oauth/profile', $this->headers(), null);
        $profile = $this->payload('GET', '/api/oauth/profile', $response);

        $organization = $profile['organization'] ?? null;
        $uuid = is_array($organization) ? ($organization['uuid'] ?? null) : null;

        return is_string($uuid) && trim($uuid) !== '' ? trim($uuid) : null;
    }

    public function reset(string $organizationUuid): LimitResetResult
    {
        if (trim($organizationUuid) === '') {
            throw new \InvalidArgumentException('organizationUuid must not be empty.');
        }

        $path = '/api/organizations/'.rawurlencode(trim($organizationUuid)).'/reset_rate_limits';
        $response = ($this->http)(
            'POST',
            $this->baseUrl.$path,
            $this->headers(),
            json_encode(['program' => self::PROGRAM], JSON_THROW_ON_ERROR),
        );
        $payload = $this->payload('POST', $path, $response);

        return LimitResetResult::fromArray($payload);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'anthropic-beta' => self::OAUTH_BETA,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ];
    }

    /**
     * @param  array{status: int, body: string}|null  $response
     * @return array<string, mixed>
     */
    private function payload(string $method, string $path, ?array $response): array
    {
        if ($response === null) {
            throw new LimitResetFailed(sprintf('Anthropic did not answer %s %s.', $method, $path));
        }

        $status = $response['status'] ?? 0;

        if ($status === 401 || $status === 403) {
            throw new LimitResetFailed(
                sprintf('Anthropic rejected %s %s: the OAuth token is stale or misses the required scope.', $method, $path),
                $status,
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new LimitResetFailed(
                sprintf('Anthropic rejected %s %s with HTTP %d.', $method, $path, $status),
                $status,
            );
        }

        $decoded = json_decode($response['body'] ?? '', true);

        if (! is_array($decoded)) {
            throw new LimitResetFailed(sprintf('Anthropic returned an invalid response for %s %s.', $method, $path));
        }

        return $decoded;
    }

    /** @return callable(string, string, array<string, string>, string|null): (array{status: int, body: string}|null) */
    private function defaultSender(): callable
    {
        $timeout = $this->timeout ?? 10.0;

        return static function (string $method, string $url, array $headers, ?string $body) use ($timeout): ?array {
            foreach (self::proxyHeaders($url) as $name => $value) {
                $headers[$name] ??= $value;
            }

            $lines = [];

            foreach ($headers as $name => $value) {
                $lines[] = $name.': '.$value;
            }

            $options = [
                'method' => $method,
                'header' => implode("\r\n", $lines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ];

            if (($proxy = self::proxyFor($url)) !== null) {
                $options['proxy'] = $proxy;
                $options['request_fulluri'] = true;
            }

            $context = stream_context_create(['http' => $options]);

            $responseBody = @file_get_contents($url, false, $context);

            if ($responseBody === false) {
                return null;
            }

            $headline = $http_response_header[0] ?? '';

            if (preg_match('/\s(\d{3})\s/', (string) $headline, $matches) === 1) {
                $status = (int) $matches[1];
            } else {
                $status = 0;
            }

            return ['status' => $status, 'body' => (string) $responseBody];
        };
    }

    /**
     * The `tcp://` proxy PHP streams need, from the standard proxy
     * environment, or null for a direct connection.
     */
    private static function proxyFor(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        foreach (preg_split('/[\s,]+/', (string) (getenv('NO_PROXY') ?: getenv('no_proxy') ?: '')) ?: [] as $rule) {
            $rule = strtolower(trim((string) $rule, '. '));

            if ($rule === '') {
                continue;
            }

            if ($rule === '*' || $host === $rule || str_ends_with($host, '.'.$rule)) {
                return null;
            }
        }

        $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy') ?: getenv('HTTP_PROXY') ?: getenv('http_proxy') ?: null;

        if (! is_string($proxy) || trim($proxy) === '') {
            return null;
        }

        $parts = parse_url(str_contains($proxy, '://') ? $proxy : 'http://'.$proxy);

        if (! is_array($parts) || ($parts['host'] ?? '') === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return 'tcp://'.$parts['host'].$port;
    }

    /** @return array<string, string> */
    private static function proxyHeaders(string $url): array
    {
        if (self::proxyFor($url) === null) {
            return [];
        }

        $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy') ?: getenv('HTTP_PROXY') ?: getenv('http_proxy') ?: null;
        $parts = is_string($proxy) ? parse_url(str_contains($proxy, '://') ? $proxy : 'http://'.$proxy) : null;
        $credentials = ($parts['user'] ?? null) !== null
            ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '')
            : null;

        return $credentials !== null && $credentials !== ''
            ? ['Proxy-Authorization' => 'Basic '.base64_encode($credentials)]
            : [];
    }
}
