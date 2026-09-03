<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\Claude;
use Develate\ClaudecodeCli\Tests\Support\FakeTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Value\PermissionMode;
use PHPUnit\Framework\TestCase;

final class ClaudeTest extends TestCase
{
    public function testEntryPointAppliesDefaultsAndCreatesSession(): void
    {
        $transport = new FakeTransport();
        $claude = new Claude('unused', $transport, timeout: 12.0, version: '2.1.258');

        $session = $claude
            ->in('/project')
            ->dangerouslyBypassPermissions()
            ->session(
                model: 'sonnet',
                additionalDirectories: ['/shared'],
                tools: ['Read'],
                allowedTools: ['Read'],
            );
        $session->query('hello');

        $request = $transport->requests[0];
        self::assertSame('/project', $request->cwd);
        self::assertSame('sonnet', $request->model);
        self::assertSame(PermissionMode::BypassPermissions, $request->permissionMode);
        self::assertSame(['/shared'], $request->additionalDirectories);
        self::assertSame(['Read'], $request->tools);
        self::assertSame(12.0, $request->timeout);
        self::assertSame('2.1.258', $claude->version());
    }

    public function testExplicitSessionIdStartsWithSessionIdAndResumeUsesIt(): void
    {
        $transport = new FakeTransport();
        $claude = new Claude('unused', $transport, version: '2.1.0');

        $claude->session(sessionId: 'custom-id')->query('start');
        $claude->resume('existing-id')->query('continue');

        self::assertSame(RunMode::Start, $transport->requests[0]->mode);
        self::assertSame('custom-id', $transport->requests[0]->sessionId);
        self::assertSame(RunMode::Resume, $transport->requests[1]->mode);
        self::assertSame('existing-id', $transport->requests[1]->sessionId);
    }
}
