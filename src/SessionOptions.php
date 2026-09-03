<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Value\Effort;
use Develate\ClaudecodeCli\Value\PermissionMode;
use Develate\ClaudecodeCli\Value\PermissionPrompts;
use Develate\ClaudecodeCli\Value\SettingSource;

/**
 * Everything that describes a session rather than a single run.
 *
 * These values are stable for the lifetime of a session and are re-sent on every
 * start, resume and fork: Claude Code does not remember them across processes.
 *
 * Three of the fields distinguish "unset" from "empty", because Claude Code
 * treats those cases differently:
 *
 * - `tools`: `null` leaves the built-in set alone, `[]` disables every tool.
 * - `settingSources`: `null` loads the usual files, `[]` loads none of them.
 * - `chrome`: `null` leaves the integration alone, `false` disables it.
 */
final readonly class SessionOptions
{
    /**
     * @param  list<string>  $additionalDirectories  extra roots the file tools may reach
     * @param  list<string>|null  $tools  the built-in tools to expose, or null for the default set
     * @param  list<string>  $allowedTools  pre-approved tool patterns, e.g. `Bash(git *)`
     * @param  list<string>  $disallowedTools  denied tool patterns
     * @param  list<SettingSource>|null  $settingSources  settings files to load, or null for the default
     * @param  string|null  $settings  a settings file path or a settings JSON string
     * @param  list<string>  $mcpConfig  MCP config file paths or JSON strings
     * @param  bool  $strictMcpConfig  ignore every MCP server that did not come from $mcpConfig
     * @param  string|null  $permissionPromptTool  MCP tool that answers permission prompts
     * @param  bool|null  $chrome  null leaves the Chrome integration alone, false disables it
     * @param  bool  $disableSlashCommands  drop every skill, including those a project ships
     */
    public function __construct(
        public string $cwd,
        public ?string $model = null,
        public ?Effort $effort = null,
        public PermissionMode $permissionMode = PermissionMode::Default,
        public array $additionalDirectories = [],
        public ?array $tools = null,
        public array $allowedTools = [],
        public array $disallowedTools = [],
        public ?array $settingSources = null,
        public ?string $settings = null,
        public array $mcpConfig = [],
        public bool $strictMcpConfig = false,
        public ?string $permissionPromptTool = null,
        public ?PermissionPrompts $permissionPrompts = null,
        public ?string $systemPrompt = null,
        public ?string $appendSystemPrompt = null,
        public ?bool $chrome = null,
        public bool $disableSlashCommands = false,
        public bool $forwardSubagentText = false,
        public bool $includePartialMessages = true,
        public ?string $sessionId = null,
    ) {
        if ($cwd === '') {
            throw new \InvalidArgumentException('cwd must not be empty.');
        }
    }

    public function withCwd(string $cwd): self
    {
        return $this->with(cwd: $cwd);
    }

    public function withModel(?string $model): self
    {
        return $this->with(model: $model);
    }

    public function withEffort(?Effort $effort): self
    {
        return $this->with(effort: $effort);
    }

    public function withPermissionMode(PermissionMode $permissionMode): self
    {
        return $this->with(permissionMode: $permissionMode);
    }

    public function withSessionId(?string $sessionId): self
    {
        return $this->with(sessionId: $sessionId);
    }

    /**
     * @param  list<string>  $mcpConfig
     */
    public function withMcpConfig(array $mcpConfig, ?bool $strict = null): self
    {
        return $this->with(mcpConfig: $mcpConfig, strictMcpConfig: $strict ?? $this->strictMcpConfig);
    }

    public function withAppendSystemPrompt(?string $appendSystemPrompt): self
    {
        return $this->with(appendSystemPrompt: $appendSystemPrompt);
    }

    /**
     * @param  list<string>  $additionalDirectories
     * @param  list<string>|null  $tools
     * @param  list<string>|null  $allowedTools
     * @param  list<string>|null  $disallowedTools
     * @param  list<SettingSource>|null  $settingSources
     * @param  list<string>|null  $mcpConfig
     */
    public function with(
        ?string $cwd = null,
        ?string $model = null,
        ?Effort $effort = null,
        ?PermissionMode $permissionMode = null,
        ?array $additionalDirectories = null,
        ?array $tools = null,
        ?array $allowedTools = null,
        ?array $disallowedTools = null,
        ?array $settingSources = null,
        ?string $settings = null,
        ?array $mcpConfig = null,
        ?bool $strictMcpConfig = null,
        ?string $permissionPromptTool = null,
        ?PermissionPrompts $permissionPrompts = null,
        ?string $systemPrompt = null,
        ?string $appendSystemPrompt = null,
        ?bool $chrome = null,
        ?bool $disableSlashCommands = null,
        ?bool $forwardSubagentText = null,
        ?bool $includePartialMessages = null,
        ?string $sessionId = null,
    ): self {
        return new self(
            cwd: $cwd ?? $this->cwd,
            model: $model ?? $this->model,
            effort: $effort ?? $this->effort,
            permissionMode: $permissionMode ?? $this->permissionMode,
            additionalDirectories: $additionalDirectories ?? $this->additionalDirectories,
            tools: $tools ?? $this->tools,
            allowedTools: $allowedTools ?? $this->allowedTools,
            disallowedTools: $disallowedTools ?? $this->disallowedTools,
            settingSources: $settingSources ?? $this->settingSources,
            settings: $settings ?? $this->settings,
            mcpConfig: $mcpConfig ?? $this->mcpConfig,
            strictMcpConfig: $strictMcpConfig ?? $this->strictMcpConfig,
            permissionPromptTool: $permissionPromptTool ?? $this->permissionPromptTool,
            permissionPrompts: $permissionPrompts ?? $this->permissionPrompts,
            systemPrompt: $systemPrompt ?? $this->systemPrompt,
            appendSystemPrompt: $appendSystemPrompt ?? $this->appendSystemPrompt,
            chrome: $chrome ?? $this->chrome,
            disableSlashCommands: $disableSlashCommands ?? $this->disableSlashCommands,
            forwardSubagentText: $forwardSubagentText ?? $this->forwardSubagentText,
            includePartialMessages: $includePartialMessages ?? $this->includePartialMessages,
            sessionId: $sessionId ?? $this->sessionId,
        );
    }
}
