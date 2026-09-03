<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\Claude;
use Develate\ClaudecodeCli\SessionOptions;
use Develate\ClaudecodeCli\Tests\Support\FakeTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Value\PermissionMode;
use PHPUnit\Framework\TestCase;

final class ClaudeTest extends TestCase
{
    public function testEntryPointAppliesDefaultsAndCreatesSession(): void
    {
        $transport = new FakeTransport;
        $claude = new Claude('unused', $transport, timeout: 12.0, version: '2.1.259');

        $session = $claude
            ->in('/project')
            ->dangerouslyBypassPermissions()
            ->session(new SessionOptions(
                cwd: '/project',
                model: 'sonnet',
                additionalDirectories: ['/shared'],
                tools: ['Read'],
                allowedTools: ['Read'],
            ));
        $session->query('hello');

        $request = $transport->requests[0];
        self::assertSame('/project', $request->cwd());
        self::assertSame('sonnet', $request->session->model);
        self::assertSame(PermissionMode::BypassPermissions, $request->session->permissionMode);
        self::assertSame(['/shared'], $request->session->additionalDirectories);
        self::assertSame(['Read'], $request->session->tools);
        self::assertSame(12.0, $request->timeout());
        self::assertSame('2.1.259', $claude->version());
    }

    public function testBypassDoesNotOverrideAnExplicitPermissionMode(): void
    {
        $transport = new FakeTransport;
        $claude = (new Claude('unused', $transport, version: '2.1.259'))->dangerouslyBypassPermissions();

        $claude->session(new SessionOptions('/project', permissionMode: PermissionMode::Plan))->query('hello');

        self::assertSame(PermissionMode::Plan, $transport->requests[0]->session->permissionMode);
    }

    public function testExplicitSessionIdStartsWithSessionIdAndResumeUsesIt(): void
    {
        $transport = new FakeTransport;
        $claude = new Claude('unused', $transport, version: '2.1.0');

        $claude->session(new SessionOptions('/project', sessionId: 'custom-id'))->query('start');
        $claude->resume('existing-id', new SessionOptions('/project'))->query('continue');

        self::assertSame(RunMode::Start, $transport->requests[0]->mode);
        self::assertSame('custom-id', $transport->requests[0]->sessionId);
        self::assertSame(RunMode::Resume, $transport->requests[1]->mode);
        self::assertSame('existing-id', $transport->requests[1]->sessionId);
    }

    public function testVersionRunsUnderTheConfiguredEnvironment(): void
    {
        $claude = new Claude(
            binary: self::stub('fwrite(STDOUT, getenv("CLAUDE_STUB_VERSION"));'),
            env: ['CLAUDE_STUB_VERSION' => '2.1.259 (Claude Code)'],
        );

        self::assertSame('2.1.259', $claude->version());
    }

    public function testAuthStatusParsesTheJsonPayload(): void
    {
        $claude = new Claude(
            binary: self::stub('fwrite(STDOUT, getenv("CLAUDE_STUB_JSON"));'),
            version: '2.1.259',
            env: ['CLAUDE_STUB_JSON' => json_encode([
                'loggedIn' => true,
                'authMethod' => 'claudeai',
                'apiProvider' => 'firstParty',
                'email' => 'user@example.com',
                'projectsDirectory' => '/home/user/.claude/projects',
            ])],
        );

        $status = $claude->authStatus();

        self::assertTrue($status->loggedIn);
        self::assertTrue($status->isOauth());
        self::assertSame('claudeai', $status->authMethod);
        self::assertSame('user@example.com', $status->email);
        self::assertSame('/home/user/.claude/projects', $status->projectsDirectory);
        self::assertArrayHasKey('apiProvider', $status->raw());
    }

    public function testAuthStatusReadsJsonEvenWhenTheCommandExitsNonZero(): void
    {
        $claude = new Claude(
            binary: self::stub('fwrite(STDOUT, \'{"loggedIn":false,"authMethod":"none"}\'); exit(1);'),
            version: '2.1.259',
        );

        $status = $claude->authStatus();

        self::assertFalse($status->loggedIn);
        self::assertFalse($status->isOauth());
        self::assertSame('none', $status->authMethod);
    }

    /**
     * An executable stand-in for the Claude binary.
     */
    private static function stub(string $php): string
    {
        $path = sys_get_temp_dir().'/claude-stub-'.substr(sha1($php), 0, 12).'.sh';
        file_put_contents($path, sprintf("#!/bin/sh\nexec %s -r %s\n", escapeshellarg(PHP_BINARY), escapeshellarg($php)));
        chmod($path, 0700);

        return $path;
    }
}
