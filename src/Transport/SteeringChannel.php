<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Transport;

use Develate\ClaudecodeCli\Message\UserMessage;
use Develate\ClaudecodeCli\StreamItem;

/**
 * User messages written to a running process' stdin.
 *
 * Claude Code keeps reading stdin while it works and hands a message to the
 * model at the next tool boundary, inside the running turn. One that arrives
 * after the last tool call is answered as a follow-up turn of the same run.
 * Either way `--replay-user-messages` echoes it back under the uuid it was
 * sent with once the model has it; until then stdin must stay open, or the
 * message is lost.
 */
final class SteeringChannel
{
    /** Seconds between ticks; the process itself is polled far more often. */
    private const TICK_INTERVAL = 0.25;

    private bool $open = false;

    private bool $closed = false;

    /** @var list<string> */
    private array $unwritten = [];

    /** @var array<string, true> */
    private array $outstanding = [];

    /** @var (\Closure(): void)|null */
    private ?\Closure $tickHandler = null;

    private ?float $lastTick = null;

    /**
     * Runs between reads, including while the model is silently thinking, at
     * most every quarter of a second.
     *
     * @param (callable(): void)|null $handler
     */
    public function onTick(?callable $handler): void
    {
        $this->tickHandler = $handler === null ? null : $handler(...);
    }

    /**
     * Queues a user message for the running turn.
     *
     * @param list<string> $images
     * @return string the message uuid, repeated by the replay that confirms it
     */
    public function steer(string $prompt, array $images = [], ?string $uuid = null): string
    {
        if (!$this->open || $this->closed) {
            throw new \LogicException('Steering requires an active run.');
        }
        if (trim($prompt) === '' && $images === []) {
            throw new \InvalidArgumentException('A steering message must contain text or images.');
        }

        $uuid ??= self::uuid();
        if (isset($this->outstanding[$uuid])) {
            throw new \InvalidArgumentException("A steering message with uuid {$uuid} is already waiting.");
        }

        $this->unwritten[] = UserInput::line($prompt, $images, $uuid);
        $this->outstanding[$uuid] = true;

        return $uuid;
    }

    public function isOpen(): bool
    {
        return $this->open && !$this->closed;
    }

    /** @internal Called by the transport once stdin accepts input. */
    public function open(): void
    {
        $this->open = true;
    }

    /**
     * @internal Runs the tick handler and returns what is due on stdin.
     */
    public function input(): string
    {
        if (!$this->isOpen()) {
            return '';
        }
        $now = microtime(true);
        if ($this->tickHandler !== null && ($this->lastTick === null || $now - $this->lastTick >= self::TICK_INTERVAL)) {
            $this->lastTick = $now;
            ($this->tickHandler)();
        }

        $input = implode('', $this->unwritten);
        $this->unwritten = [];

        return $input;
    }

    /** @internal */
    public function observe(StreamItem $item): void
    {
        if ($item instanceof UserMessage && $item->isReplay && $item->uuid !== null) {
            unset($this->outstanding[$item->uuid]);
        }
    }

    /**
     * Whether every message sent has reached the model, so stdin may close.
     */
    public function isSettled(): bool
    {
        return $this->unwritten === [] && $this->outstanding === [];
    }

    /** @internal Called by the transport once stdin closes. */
    public function close(): void
    {
        $this->closed = true;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
