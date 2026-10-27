<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Transport;

use Develate\ClaudecodeCli\Parser\StreamJsonParser;
use Develate\ClaudecodeCli\Transport\BackgroundTaskWait;
use PHPUnit\Framework\TestCase;

final class BackgroundTaskWaitTest extends TestCase
{
    public function testIsDoneAfterAResultWithNothingRunning(): void
    {
        $wait = new BackgroundTaskWait;
        self::assertFalse($wait->isDone());

        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');

        self::assertTrue($wait->isDone());
    }

    public function testWaitsForTheResultThatFollowsTheLastBackgroundTask(): void
    {
        $wait = new BackgroundTaskWait;

        $this->observe($wait, '{"type":"system","subtype":"background_tasks_changed","tasks":[{"task_id":"t1"}]}');
        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');
        self::assertFalse($wait->isDone());

        // The task finishing is not enough: the model has yet to answer it.
        $this->observe($wait, '{"type":"system","subtype":"background_tasks_changed","tasks":[]}');
        self::assertFalse($wait->isDone());

        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');
        self::assertTrue($wait->isDone());
    }

    public function testAMessageReceivedAfterTheResultWaitsForItsAnswer(): void
    {
        $wait = new BackgroundTaskWait;

        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');
        $this->observe($wait, '{"type":"user","message":{"role":"user","content":"Also update the docs"},"uuid":"u1","isReplay":true}');
        self::assertFalse($wait->isDone());

        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');
        self::assertTrue($wait->isDone());
    }

    public function testGivesUpOnceTheLimitIsSpent(): void
    {
        $now = 100.0;
        $wait = new BackgroundTaskWait(30.0, static function () use (&$now): float {
            return $now;
        });

        $this->observe($wait, '{"type":"system","subtype":"background_tasks_changed","tasks":[{"task_id":"t1"}]}');
        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');

        $now = 129.0;
        self::assertFalse($wait->isDone());
        self::assertFalse($wait->isExpired());
        $now = 130.0;
        self::assertTrue($wait->isDone());
        self::assertTrue($wait->isExpired());
    }

    public function testASettledResultNeverExpires(): void
    {
        $now = 100.0;
        $wait = new BackgroundTaskWait(30.0, static function () use (&$now): float {
            return $now;
        });

        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');

        $now = 1000.0;
        self::assertTrue($wait->isDone());
        self::assertFalse($wait->isExpired());
    }

    public function testTheClockStopsOnceTheTasksHaveFinished(): void
    {
        $now = 100.0;
        $wait = new BackgroundTaskWait(30.0, static function () use (&$now): float {
            return $now;
        });

        $this->observe($wait, '{"type":"system","subtype":"background_tasks_changed","tasks":[{"task_id":"t1"}]}');
        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');
        $now = 120.0;
        $this->observe($wait, '{"type":"system","subtype":"background_tasks_changed","tasks":[]}');

        // The model is answering the finished task; that is not waiting.
        $now = 200.0;
        self::assertFalse($wait->isExpired());
        self::assertFalse($wait->isDone());

        $this->observe($wait, '{"type":"result","subtype":"success","is_error":false,"session_id":"s1"}');
        self::assertTrue($wait->isDone());
    }

    private function observe(BackgroundTaskWait $wait, string $line): void
    {
        foreach ((new StreamJsonParser)->parseLine($line) as $item) {
            $wait->observe($item);
        }
    }
}
