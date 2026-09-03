<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Transport;

use Develate\ClaudecodeCli\RunOptions;
use Develate\ClaudecodeCli\SessionOptions;
use Develate\ClaudecodeCli\Transport\ExecTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Value\Effort;
use Develate\ClaudecodeCli\Value\PermissionMode;
use Develate\ClaudecodeCli\Value\PermissionPrompts;
use Develate\ClaudecodeCli\Value\SettingSource;
use PHPUnit\Framework\TestCase;

final class ExecTransportTest extends TestCase
{
    public function testBuildsNativeClaudeCommand(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            mode: RunMode::Start,
            sessionId: '550e8400-e29b-41d4-a716-446655440000',
            prompt: 'Fix it.',
            session: new SessionOptions(
                cwd: '/project',
                model: 'sonnet',
                effort: Effort::High,
                permissionMode: PermissionMode::AcceptEdits,
                additionalDirectories: ['/shared'],
                tools: ['Read', 'Edit', 'Bash'],
                allowedTools: ['Read', 'Bash(composer *)'],
                disallowedTools: ['Bash(rm *)'],
            ),
            run: new RunOptions(maxTurns: 10, maxBudgetUsd: 2.0, schema: ['type' => 'object']),
        ));

        self::assertSame('claude', $command[0]);
        self::assertContains('--include-partial-messages', $command);
        self::assertContains('--session-id', $command);
        self::assertSame('high', self::valueOf($command, '--effort'));
        self::assertSame('acceptEdits', self::valueOf($command, '--permission-mode'));
        self::assertSame('/shared', self::valueOf($command, '--add-dir'));
        self::assertSame('Read,Edit,Bash', self::valueOf($command, '--tools'));
        self::assertSame('Read,Bash(composer *)', self::valueOf($command, '--allowedTools'));
        self::assertSame('Bash(rm *)', self::valueOf($command, '--disallowedTools'));
        self::assertSame('10', self::valueOf($command, '--max-turns'));
        self::assertSame('2', self::valueOf($command, '--max-budget-usd'));
        self::assertJson((string) self::valueOf($command, '--json-schema'));
    }

    public function testPromptTravelsOnStdinRatherThanAsAnArgument(): void
    {
        $transport = new ExecTransport;
        $request = new RunRequest(
            RunMode::Start,
            null,
            'Fix it.',
            new SessionOptions('/project', additionalDirectories: ['/shared']),
            new RunOptions(images: ['/tmp/reference.png']),
        );

        $command = $transport->command($request);

        self::assertNotContains('Fix it.', $command);
        self::assertStringStartsWith('Fix it.', $transport->input($request));
        self::assertStringContainsString('/tmp/reference.png', $transport->input($request));
    }

    public function testSendsAnEmptySettingSourcesListRatherThanOmittingIt(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            RunMode::Start,
            null,
            'Go.',
            new SessionOptions(
                cwd: '/project',
                tools: [],
                settingSources: [],
                settings: '{"fastMode":true}',
                mcpConfig: ['{"mcpServers":{}}', '/tmp/servers.json'],
                strictMcpConfig: true,
                permissionPromptTool: 'mcp__develate_permissions__approve',
                permissionPrompts: PermissionPrompts::Host,
                appendSystemPrompt: 'Project notes.',
                chrome: false,
                disableSlashCommands: true,
                forwardSubagentText: true,
            ),
        ));

        self::assertSame('', self::valueOf($command, '--setting-sources'));
        self::assertSame('', self::valueOf($command, '--tools'));
        self::assertSame('{"fastMode":true}', self::valueOf($command, '--settings'));
        self::assertSame('{"mcpServers":{}}', self::valueOf($command, '--mcp-config'));
        self::assertContains('/tmp/servers.json', $command);
        self::assertContains('--strict-mcp-config', $command);
        self::assertSame('mcp__develate_permissions__approve', self::valueOf($command, '--permission-prompt-tool'));
        self::assertSame('host', self::valueOf($command, '--permission-prompts'));
        self::assertSame('Project notes.', self::valueOf($command, '--append-system-prompt'));
        self::assertContains('--no-chrome', $command);
        self::assertContains('--disable-slash-commands', $command);
        self::assertContains('--forward-subagent-text', $command);
    }

    public function testKnownSettingSourcesAreSentAsAList(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            RunMode::Start,
            null,
            'Go.',
            new SessionOptions('/project', settingSources: [SettingSource::User, SettingSource::Project]),
        ));

        self::assertSame('user,project', self::valueOf($command, '--setting-sources'));
    }

    public function testPartialMessagesCanBeTurnedOff(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            RunMode::Start,
            null,
            'Go.',
            new SessionOptions('/project', includePartialMessages: false),
        ));

        self::assertNotContains('--include-partial-messages', $command);
    }

    public function testBuildsForkAsResumeWithForkFlag(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            RunMode::Fork,
            'session-parent',
            'Try another way.',
            new SessionOptions('/project'),
        ));

        self::assertContains('--resume', $command);
        self::assertContains('session-parent', $command);
        self::assertContains('--fork-session', $command);
        self::assertNotContains('--permission-mode', $command);
        self::assertNotContains('--setting-sources', $command);
    }

    public function testResumeWithoutASessionIdIsRejected(): void
    {
        $this->expectException(\LogicException::class);

        (new ExecTransport)->command(new RunRequest(
            RunMode::Resume,
            null,
            'Continue.',
            new SessionOptions('/project'),
        ));
    }

    /** @param list<string> $command */
    private static function valueOf(array $command, string $flag): ?string
    {
        $position = array_search($flag, $command, true);

        return $position === false ? null : ($command[$position + 1] ?? null);
    }
}
