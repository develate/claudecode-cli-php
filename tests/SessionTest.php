<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\RunOptions;
use Develate\ClaudecodeCli\Session;
use Develate\ClaudecodeCli\SessionOptions;
use Develate\ClaudecodeCli\Tests\Support\FakeTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Value\Effort;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function testQueryUsesStreamAndThenResumesSession(): void
    {
        $transport = new FakeTransport;
        $session = $this->session($transport);

        $first = $session->query('first', new RunOptions(maxTurns: 3, maxBudgetUsd: 1.5));
        $second = $session->query('second');

        self::assertSame('answer: first', $first->text);
        self::assertSame('session-one', $session->id());
        self::assertSame(3, $transport->requests[0]->run->maxTurns);
        self::assertSame(RunMode::Start, $transport->requests[0]->mode);
        self::assertSame(RunMode::Resume, $transport->requests[1]->mode);
        self::assertSame('session-one', $transport->requests[1]->sessionId);
        self::assertSame('answer: second', $second->text);
    }

    public function testRunIsLazyAndResultDrainsIt(): void
    {
        $transport = new FakeTransport;
        $session = $this->session($transport);

        $run = $session->stream('lazy');
        self::assertSame([], $transport->requests);
        self::assertSame('answer: lazy', $run->result()->text);
        self::assertTrue($run->isComplete());
        self::assertCount(2, $run->items());
        self::assertCount(2, $run->messages());
        self::assertSame([], $run->events());
    }

    public function testForkKeepsParentAndReceivesNewId(): void
    {
        $transport = new FakeTransport;
        $session = $this->session($transport);
        $session->query('first');

        $branch = $session->fork();
        self::assertNull($branch->id());
        $result = $branch->query('alternative');

        self::assertSame(RunMode::Fork, $transport->requests[1]->mode);
        self::assertSame('session-one', $transport->requests[1]->sessionId);
        self::assertSame('session-child', $branch->id());
        self::assertSame('session-child', $result->sessionId);
        self::assertSame('session-one', $session->id());
    }

    public function testWithKeepsTheConversationAndChangesTheOptions(): void
    {
        $transport = new FakeTransport;
        $session = $this->session($transport);
        $session->query('first');

        $session->with($session->options()->withEffort(Effort::High)->withModel('opus'))->query('second');

        self::assertSame(RunMode::Resume, $transport->requests[1]->mode);
        self::assertSame('session-one', $transport->requests[1]->sessionId);
        self::assertSame(Effort::High, $transport->requests[1]->session->effort);
        self::assertSame('opus', $transport->requests[1]->session->model);
    }

    public function testSessionTimeoutAppliesWhenTheRunDoesNotSetOne(): void
    {
        $transport = new FakeTransport;
        $session = new Session($transport, '2.1.259', new SessionOptions('/project'), timeout: 30.0);

        $session->query('first');
        $session->query('second', new RunOptions(timeout: 5.0));

        self::assertSame(30.0, $transport->requests[0]->timeout());
        self::assertSame(5.0, $transport->requests[1]->timeout());
    }

    public function testRejectsInvalidLimits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RunOptions(maxTurns: 0);
    }

    private function session(FakeTransport $transport): Session
    {
        return new Session(
            transport: $transport,
            claudeVersion: '2.1.0',
            options: new SessionOptions(cwd: '/project', model: 'sonnet'),
        );
    }
}
