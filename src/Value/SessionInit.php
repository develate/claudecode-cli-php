<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

use Develate\ClaudecodeCli\Message\SystemMessage;

/**
 * The `system`/`init` envelope Claude Code emits before its first message.
 *
 * It is the only place a run states what it actually resolved: the model behind
 * an alias, the tools that survived the flags, the MCP servers that connected
 * and where the credentials came from.
 */
final readonly class SessionInit
{
    /**
     * @param  list<string>  $tools
     * @param  list<array<string, mixed>>  $mcpServers
     * @param  list<string>  $agents
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $sessionId,
        public ?string $model,
        public ?string $cwd,
        public ?string $permissionMode,
        public array $tools,
        public array $mcpServers,
        public array $agents,
        public ?string $apiKeySource,
        public ?string $claudeCodeVersion,
        public ?string $outputStyle,
        public ?string $fastModeState,
        public ?string $fastModeDisabledReason,
        private array $raw = [],
    ) {}

    public static function fromSystemMessage(SystemMessage $message): ?self
    {
        if ($message->subtype !== 'init') {
            return null;
        }

        $raw = $message->data;

        return new self(
            sessionId: $message->sessionId ?? '',
            model: self::string($raw['model'] ?? null),
            cwd: self::string($raw['cwd'] ?? null),
            permissionMode: self::string($raw['permissionMode'] ?? null),
            tools: self::strings($raw['tools'] ?? null),
            mcpServers: self::maps($raw['mcp_servers'] ?? null),
            agents: self::strings($raw['agents'] ?? null),
            apiKeySource: self::string($raw['apiKeySource'] ?? null),
            claudeCodeVersion: self::string($raw['claude_code_version'] ?? null),
            outputStyle: self::string($raw['output_style'] ?? null),
            fastModeState: self::string($raw['fast_mode_state'] ?? null),
            fastModeDisabledReason: self::string($raw['fast_mode_disabled_reason'] ?? null),
            raw: $raw,
        );
    }

    public function hasTool(string $name): bool
    {
        return in_array($name, $this->tools, true);
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /** @return list<array<string, mixed>> */
    private static function maps(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
