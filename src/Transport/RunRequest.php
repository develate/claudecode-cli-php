<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\Value\PermissionMode;

final readonly class RunRequest
{
    /**
     * @param list<string> $additionalDirectories
     * @param list<string>|null $tools
     * @param list<string> $allowedTools
     * @param list<string> $disallowedTools
     * @param array<string, mixed>|null $schema
     * @param list<string> $images
     */
    public function __construct(
        public RunMode $mode,
        public ?string $sessionId,
        public string $prompt,
        public string $cwd,
        public ?string $model,
        public PermissionMode $permissionMode,
        public array $additionalDirectories = [],
        public ?array $tools = null,
        public array $allowedTools = [],
        public array $disallowedTools = [],
        public ?int $maxTurns = null,
        public ?float $maxBudgetUsd = null,
        public ?array $schema = null,
        public array $images = [],
        public ?float $timeout = null,
        public ?\Closure $isCancelled = null,
    ) {
    }
}
