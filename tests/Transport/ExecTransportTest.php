<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Transport;

use Develate\ClaudecodeCli\Transport\ExecTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Value\PermissionMode;
use PHPUnit\Framework\TestCase;

final class ExecTransportTest extends TestCase
{
    public function testBuildsNativeClaudeCommand(): void
    {
        $request = new RunRequest(
            mode: RunMode::Start,
            sessionId: '550e8400-e29b-41d4-a716-446655440000',
            prompt: 'Fix it.',
            cwd: '/project',
            model: 'sonnet',
            permissionMode: PermissionMode::AcceptEdits,
            additionalDirectories: ['/shared'],
            tools: ['Read', 'Edit', 'Bash'],
            allowedTools: ['Read', 'Bash(composer *)'],
            disallowedTools: ['Bash(rm *)'],
            maxTurns: 10,
            maxBudgetUsd: 2.0,
            schema: ['type' => 'object'],
            images: ['/tmp/reference.png'],
        );

        $command = (new ExecTransport())->command($request);

        self::assertSame('claude', $command[0]);
        self::assertContains('--include-partial-messages', $command);
        self::assertContains('--session-id', $command);
        self::assertContains('Read,Edit,Bash', $command);
        self::assertContains('2', $command);
        self::assertStringContainsString('/tmp/reference.png', $command[array_key_last($command)]);
        self::assertJson($command[array_search('--json-schema', $command, true) + 1]);
    }

    public function testBuildsForkAsResumeWithForkFlag(): void
    {
        $request = new RunRequest(
            RunMode::Fork,
            'session-parent',
            'Try another way.',
            '/project',
            null,
            PermissionMode::Default,
        );

        $command = (new ExecTransport())->command($request);

        self::assertContains('--resume', $command);
        self::assertContains('session-parent', $command);
        self::assertContains('--fork-session', $command);
        self::assertNotContains('--permission-mode', $command);
    }
}
