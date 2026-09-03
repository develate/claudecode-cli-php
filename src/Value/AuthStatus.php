<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

/**
 * The typed view of `claude auth status --json`.
 *
 * Claude Code adds fields to this payload between releases, so everything it
 * reports is kept in `raw` and only the stable core is promoted to properties.
 */
final readonly class AuthStatus
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $loggedIn,
        public string $authMethod,
        public ?string $apiProvider,
        public ?string $email,
        public ?string $organizationName,
        public ?string $accountUuid,
        public ?string $projectsDirectory,
        private array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            loggedIn: (bool) ($raw['loggedIn'] ?? $raw['logged_in'] ?? false),
            authMethod: self::string($raw['authMethod'] ?? $raw['auth_method'] ?? null) ?? 'none',
            apiProvider: self::string($raw['apiProvider'] ?? $raw['api_provider'] ?? null),
            email: self::string($raw['email'] ?? $raw['emailAddress'] ?? ($raw['account']['email_address'] ?? null)),
            organizationName: self::string(
                $raw['organizationName'] ?? $raw['organization']['name'] ?? null
            ),
            accountUuid: self::string($raw['accountUuid'] ?? ($raw['account']['uuid'] ?? null)),
            projectsDirectory: self::string($raw['projectsDirectory'] ?? $raw['projects_directory'] ?? null),
            raw: $raw,
        );
    }

    /**
     * Whether the CLI is signed in with a Claude subscription or Console login
     * rather than an inherited API key.
     */
    public function isOauth(): bool
    {
        return $this->loggedIn && in_array($this->authMethod, ['claudeai', 'console', 'oauth'], true);
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
