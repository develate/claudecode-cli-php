<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli;

use Develate\ClaudecodeCli\Event\Event;
use Develate\ClaudecodeCli\Event\PermissionDeniedEvent;
use Develate\ClaudecodeCli\Event\RateLimitEvent;
use Develate\ClaudecodeCli\Message\AssistantMessage;
use Develate\ClaudecodeCli\Message\Message;
use Develate\ClaudecodeCli\Message\ResultMessage;
use Develate\ClaudecodeCli\Message\SystemMessage;
use Develate\ClaudecodeCli\Tool\ToolResult;
use Develate\ClaudecodeCli\Tool\ToolUse;
use Develate\ClaudecodeCli\Value\ModelUsage;
use Develate\ClaudecodeCli\Value\PermissionDenial;
use Develate\ClaudecodeCli\Value\ResultStatus;
use Develate\ClaudecodeCli\Value\RunMetadata;
use Develate\ClaudecodeCli\Value\SessionInit;
use Develate\ClaudecodeCli\Value\Usage;

final class ResultBuilder
{
    private string $sessionId;
    private ?ResultMessage $resultMessage = null;
    private ?Usage $assistantUsage = null;

    /**
     * A run answers once more for every background task that finishes after
     * a result. Each result reports only its own usage, turns and denials;
     * cost and per-model usage are already totals for the whole process.
     *
     * @var list<ResultMessage>
     */
    private array $resultMessages = [];

    private ?SessionInit $init = null;

    /** @var list<Message> */
    private array $messages = [];

    /** @var list<Event> */
    private array $events = [];

    /** @var list<ToolUse> */
    private array $toolUses = [];

    /** @var array<string, ToolResult> */
    private array $toolResults = [];

    /** @var list<string> */
    private array $reportedModels = [];

    public function __construct(string $sessionId = '')
    {
        $this->sessionId = $sessionId;
    }

    public function add(StreamItem $item): void
    {
        if ($item instanceof Message) {
            $this->messages[] = $item;
        }
        if ($item instanceof Event) {
            $this->events[] = $item;
        }
        if ($item instanceof SystemMessage) {
            if ($item->sessionId !== null) {
                $this->sessionId = $item->sessionId;
            }
            $this->init ??= SessionInit::fromSystemMessage($item);
        }
        if ($item instanceof AssistantMessage) {
            if ($item->sessionId !== null) {
                $this->sessionId = $item->sessionId;
            }
            if ($item->usage !== null) {
                $this->assistantUsage = $item->usage;
            }
            if ($item->model !== null && !in_array($item->model, $this->reportedModels, true)) {
                $this->reportedModels[] = $item->model;
            }
        }
        if ($item instanceof ResultMessage) {
            $this->resultMessage = $item;
            $this->resultMessages[] = $item;
            if ($item->sessionId !== '') {
                $this->sessionId = $item->sessionId;
            }
        }
        if ($item instanceof ToolUse) {
            $this->toolUses[] = $item;
        }
        if ($item instanceof ToolResult) {
            $this->toolResults[$item->toolUseId] = $item;
        }
    }

    public function hasResultMessage(): bool
    {
        return $this->resultMessage !== null;
    }

    public function build(RunMetadata $metadata, int $exitCode): Result
    {
        $terminal = $this->resultMessage;
        $usage = $terminal === null ? $this->assistantUsage ?? new Usage() : $this->resultUsage();
        $modelUsage = [];
        foreach ($terminal?->modelUsage ?? [] as $model => $entry) {
            if (is_string($model) && is_array($entry)) {
                $modelUsage[$model] = ModelUsage::fromArray($model, $entry);
            }
        }

        $denials = [];
        foreach ($this->resultMessages as $message) {
            foreach ($message->permissionDenials as $denial) {
                $denials[] = PermissionDenial::fromArray($denial);
            }
        }
        foreach ($this->events as $event) {
            if ($event instanceof PermissionDeniedEvent) {
                $denials[] = $event->denial;
            }
        }

        $rateLimit = null;
        foreach ($this->events as $event) {
            if ($event instanceof RateLimitEvent) {
                $rateLimit = $event->rateLimit;
            }
        }

        $subtype = $terminal?->subtype ?? '';
        $isError = $terminal?->isError ?? $exitCode !== 0;
        $terminalReason = $terminal?->terminalReason ?? match ($subtype) {
            'error_max_turns' => 'max_turns',
            'error_max_budget_usd' => 'budget_exhausted',
            'error_max_structured_output_retries' => 'structured_output_retries',
            default => null,
        };

        return new Result(
            sessionId: $this->sessionId,
            text: $this->finalText(),
            usage: $usage,
            modelUsage: $modelUsage,
            estimatedCostUsd: $terminal?->estimatedCostUsd,
            numTurns: array_sum(array_map(static fn (ResultMessage $message): int => $message->numTurns, $this->resultMessages)),
            stopReason: $terminal?->stopReason ?? $this->lastStopReason(),
            terminalReason: $terminalReason,
            status: ResultStatus::fromResult($subtype, $isError, $terminalReason),
            messages: $this->messages,
            events: $this->events,
            permissionDenials: $denials,
            structuredOutput: $terminal?->structuredOutput,
            rateLimit: $rateLimit,
            init: $this->init,
            metadata: new RunMetadata(
                $metadata->claudeVersion,
                $metadata->requestedModel,
                $metadata->cwd,
                $this->reportedModels,
            ),
            exitCode: $exitCode,
            toolUses: $this->toolUses,
            toolResults: $this->toolResults,
            raw: $terminal?->raw() ?? [],
        );
    }

    private function resultUsage(): Usage
    {
        $input = $output = $cacheRead = $cacheCreation = 0;
        foreach ($this->resultMessages as $message) {
            $input += $message->usage->inputTokens;
            $output += $message->usage->outputTokens;
            $cacheRead += $message->usage->cacheReadInputTokens;
            $cacheCreation += $message->usage->cacheCreationInputTokens;
        }

        return new Usage($input, $output, $cacheRead, $cacheCreation);
    }

    private function finalText(): string
    {
        $result = $this->resultMessage?->result;
        if ($result !== null && trim($result) !== '') {
            return $result;
        }

        $fallback = '';
        foreach ($this->messages as $message) {
            if (!$message instanceof AssistantMessage || $message->text() === '') {
                continue;
            }
            if ($message->parentToolUseId === null) {
                $fallback = $message->text();
            } elseif ($fallback === '') {
                $fallback = $message->text();
            }
        }

        return $fallback;
    }

    private function lastStopReason(): ?string
    {
        $reason = null;
        foreach ($this->messages as $message) {
            if ($message instanceof AssistantMessage && $message->stopReason !== null) {
                $reason = $message->stopReason;
            }
        }

        return $reason;
    }
}
