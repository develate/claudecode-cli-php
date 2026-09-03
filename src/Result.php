<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Content\ThinkingBlock;
use Develate\ClaudecodeCli\Event\Event;
use Develate\ClaudecodeCli\Message\AssistantMessage;
use Develate\ClaudecodeCli\Message\Message;
use Develate\ClaudecodeCli\Tool\Command;
use Develate\ClaudecodeCli\Tool\FileChange;
use Develate\ClaudecodeCli\Tool\ToolResult;
use Develate\ClaudecodeCli\Tool\ToolUse;
use Develate\ClaudecodeCli\Value\FileChangeKind;
use Develate\ClaudecodeCli\Value\ModelUsage;
use Develate\ClaudecodeCli\Value\PermissionDenial;
use Develate\ClaudecodeCli\Value\RateLimitInfo;
use Develate\ClaudecodeCli\Value\ResultStatus;
use Develate\ClaudecodeCli\Value\RunMetadata;
use Develate\ClaudecodeCli\Value\Usage;

final readonly class Result
{
    /**
     * @param array<string, ModelUsage> $modelUsage
     * @param list<Message> $messages
     * @param list<Event> $events
     * @param list<PermissionDenial> $permissionDenials
     * @param list<ToolUse> $toolUses
     * @param array<string, ToolResult> $toolResults
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $sessionId,
        public string $text,
        public Usage $usage,
        public array $modelUsage,
        public ?float $estimatedCostUsd,
        public int $numTurns,
        public ?string $stopReason,
        public ?string $terminalReason,
        public ResultStatus $status,
        public array $messages,
        public array $events,
        public array $permissionDenials,
        public mixed $structuredOutput,
        public ?RateLimitInfo $rateLimit,
        public RunMetadata $metadata,
        public int $exitCode,
        private array $toolUses,
        private array $toolResults,
        private array $raw,
    ) {
    }

    /** @return list<Message> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** @return list<ToolUse> */
    public function toolUses(): array
    {
        return $this->toolUses;
    }

    /** @return list<Command> */
    public function commands(): array
    {
        $commands = [];
        foreach ($this->toolUses as $toolUse) {
            if (strcasecmp($toolUse->name, 'Bash') !== 0) {
                continue;
            }
            $result = $this->toolResults[$toolUse->id] ?? null;
            $command = $toolUse->input['command'] ?? '';
            $commands[] = new Command(
                $toolUse->id,
                is_string($command) ? $command : '',
                $result?->output(),
                $result?->exitCode(),
            );
        }

        return $commands;
    }

    /** @return list<FileChange> */
    public function fileChanges(): array
    {
        $changes = [];
        foreach ($this->toolUses as $toolUse) {
            $kind = match (strtolower($toolUse->name)) {
                'edit' => FileChangeKind::Edit,
                'write' => FileChangeKind::Write,
                default => null,
            };
            $path = $toolUse->input['file_path'] ?? $toolUse->input['path'] ?? null;
            if ($kind !== null && is_string($path) && $path !== '') {
                $changes[] = new FileChange($toolUse->id, $path, $kind);
            }
        }

        return $changes;
    }

    /** @return list<ThinkingBlock> */
    public function thinking(): array
    {
        $thinking = [];
        foreach ($this->messages as $message) {
            if ($message instanceof AssistantMessage) {
                array_push($thinking, ...$message->thinking());
            }
        }

        return $thinking;
    }

    /** @return list<PermissionDenial> */
    public function permissionDenials(): array
    {
        return $this->permissionDenials;
    }

    /** @return list<AssistantMessage> */
    public function mainAgentMessages(): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (Message $message): bool => $message instanceof AssistantMessage && $message->parentToolUseId === null,
        ));
    }

    /** @return list<AssistantMessage> */
    public function subagentMessages(): array
    {
        return array_values(array_filter(
            $this->messages,
            static fn (Message $message): bool => $message instanceof AssistantMessage && $message->parentToolUseId !== null,
        ));
    }

    public function usageFor(string $model): ?ModelUsage
    {
        return $this->modelUsage[$model] ?? null;
    }

    public function estimatedCostForModel(string $model): ?float
    {
        return $this->usageFor($model)?->estimatedCostUsd;
    }

    public function rateLimit(): ?RateLimitInfo
    {
        return $this->rateLimit;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }
}
