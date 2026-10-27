<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Transport;

use Develate\ClaudecodeCli\Message\ResultMessage;
use Develate\ClaudecodeCli\Message\UserMessage;
use Develate\ClaudecodeCli\RunOptions;
use Develate\ClaudecodeCli\SessionOptions;
use Develate\ClaudecodeCli\StreamItem;
use Develate\ClaudecodeCli\Transport\ExecTransport;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Transport\SteeringChannel;
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
        self::assertContains('--replay-user-messages', $command);
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
        self::assertSame('stream-json', self::valueOf($command, '--input-format'));

        $input = $transport->input($request);
        $message = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
        self::assertStringEndsWith("\n", $input);
        self::assertSame('user', $message['type']);
        self::assertSame('user', $message['message']['role']);
        self::assertStringStartsWith('Fix it.', $message['message']['content']);
        self::assertStringContainsString('/tmp/reference.png', $message['message']['content']);
    }

    public function testKeepsStdinOpenUntilBackgroundTasksHaveFinished(): void
    {
        $items = $this->streamFakeClaude(<<<'PHP'
            $in = STDIN;
            fgets($in);
            emit(['type' => 'system', 'subtype' => 'background_tasks_changed', 'tasks' => [['task_id' => 't1']]]);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'STARTED', 'num_turns' => 2, 'usage' => ['input_tokens' => 3, 'output_tokens' => 4]]);
            // Claude Code before 2.1.292 stops its background tasks as soon as stdin closes.
            stream_set_blocking($in, false);
            usleep(200000);
            fread($in, 8192);
            if (feof($in)) {
                exit(0);
            }
            emit(['type' => 'system', 'subtype' => 'background_tasks_changed', 'tasks' => []]);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'FINISHED', 'num_turns' => 1, 'usage' => ['input_tokens' => 5, 'output_tokens' => 6]]);
            stream_set_blocking($in, true);
            while (fgets($in) !== false) {
            }
            PHP);

        $results = array_values(array_filter($items, static fn ($item): bool => $item instanceof ResultMessage));
        self::assertSame(['STARTED', 'FINISHED'], array_map(static fn (ResultMessage $result): ?string => $result->result, $results));
    }

    public function testWritesSteeringToStdinDuringTheTurn(): void
    {
        $steering = new SteeringChannel;
        $sent = null;
        $steering->onTick(static function () use ($steering, &$sent): void {
            $sent ??= $steering->steer('Keep the scope small', ['/tmp/ref.png'], '11111111-2222-4333-8444-555555555555');
        });

        $items = $this->streamFakeClaude(<<<'PHP'
            fgets(STDIN);
            $steer = json_decode(fgets(STDIN), true);
            emit(['type' => 'user', 'message' => $steer['message'], 'uuid' => $steer['uuid'], 'isReplay' => true]);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'DONE']);
            while (fgets(STDIN) !== false) {
            }
            PHP, steering: $steering);

        $replay = $items[0];
        self::assertInstanceOf(UserMessage::class, $replay);
        self::assertTrue($replay->isReplay);
        self::assertSame($sent, $replay->uuid);
        self::assertStringStartsWith('Keep the scope small', (string) $replay->content[0]);
        self::assertStringContainsString('/tmp/ref.png', (string) $replay->content[0]);
        self::assertFalse($steering->isOpen());
    }

    public function testKeepsStdinOpenForSteeringAnsweredAfterTheResult(): void
    {
        $steering = new SteeringChannel;
        $steering->onTick(static function () use ($steering): void {
            $steering->onTick(null);
            $steering->steer('Also update the docs', uuid: 'u1');
        });

        $items = $this->streamFakeClaude(<<<'PHP'
            $in = STDIN;
            fgets($in);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'FIRST']);
            $steer = json_decode(fgets($in), true);
            usleep(200000);
            emit(['type' => 'user', 'message' => $steer['message'], 'uuid' => $steer['uuid'], 'isReplay' => true]);
            // The follow-up must still be able to finish: stdin closing now would end the run.
            stream_set_blocking($in, false);
            usleep(200000);
            fread($in, 8192);
            if (feof($in)) {
                exit(0);
            }
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'FOLLOW-UP']);
            stream_set_blocking($in, true);
            while (fgets($in) !== false) {
            }
            PHP, steering: $steering);

        $results = array_values(array_filter($items, static fn ($item): bool => $item instanceof ResultMessage));
        self::assertSame(['FIRST', 'FOLLOW-UP'], array_map(static fn (ResultMessage $result): ?string => $result->result, $results));
        self::assertTrue($steering->isSettled());
    }

    public function testSteeringIsRejectedOnceTheRunHasEnded(): void
    {
        $steering = new SteeringChannel;
        $this->streamFakeClaude(<<<'PHP'
            fgets(STDIN);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'DONE']);
            PHP, steering: $steering);

        $this->expectException(\LogicException::class);
        $steering->steer('Too late');
    }

    public function testStopsWaitingForBackgroundTasksAtTheLimit(): void
    {
        $items = $this->streamFakeClaude(<<<'PHP'
            fgets(STDIN);
            emit(['type' => 'system', 'subtype' => 'background_tasks_changed', 'tasks' => [['task_id' => 't1']]]);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'STARTED']);
            while (fgets(STDIN) !== false) {
            }
            emit(['type' => 'system', 'subtype' => 'background_tasks_changed', 'tasks' => []]);
            PHP, new RunOptions(timeout: 5.0, maxBackgroundWait: 0.1));

        self::assertInstanceOf(ResultMessage::class, $items[1]);
        self::assertSame('STARTED', $items[1]->result);
    }

    public function testEndsTheProcessAtTheLimitWhenClosingStdinDoesNotStopTheTasks(): void
    {
        $started = microtime(true);
        $items = $this->streamFakeClaude(<<<'PHP'
            fgets(STDIN);
            emit(['type' => 'system', 'subtype' => 'background_tasks_changed', 'tasks' => [['task_id' => 't1']]]);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'STARTED']);
            // Claude Code 2.1.292 and later keep waiting for the task after stdin closes.
            while (fgets(STDIN) !== false) {
            }
            sleep(30);
            emit(['type' => 'system', 'subtype' => 'background_tasks_changed', 'tasks' => []]);
            emit(['type' => 'result', 'subtype' => 'success', 'is_error' => false, 'session_id' => 's1', 'result' => 'FINISHED']);
            PHP, new RunOptions(timeout: 20.0, maxBackgroundWait: 0.1));

        self::assertCount(2, $items);
        self::assertSame('STARTED', $items[1]->result);
        self::assertLessThan(10.0, microtime(true) - $started);
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

    public function testStartSendsTheDefaultPermissionModeExplicitly(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            RunMode::Start,
            null,
            'Go.',
            new SessionOptions('/project', permissionMode: PermissionMode::Default),
        ));

        self::assertSame('default', self::valueOf($command, '--permission-mode'));
    }

    public function testResumeSendsTheDefaultPermissionModeExplicitly(): void
    {
        $command = (new ExecTransport)->command(new RunRequest(
            RunMode::Resume,
            'session-existing',
            'Continue.',
            new SessionOptions('/project', permissionMode: PermissionMode::Default),
        ));

        self::assertSame('session-existing', self::valueOf($command, '--resume'));
        self::assertSame('default', self::valueOf($command, '--permission-mode'));
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
        self::assertSame('default', self::valueOf($command, '--permission-mode'));
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

    /** @return list<StreamItem> */
    private function streamFakeClaude(string $body, RunOptions $run = new RunOptions(timeout: 5.0), ?SteeringChannel $steering = null): array
    {
        $script = tempnam(sys_get_temp_dir(), 'fake-claude');
        $binary = tempnam(sys_get_temp_dir(), 'fake-claude');
        file_put_contents($script, "<?php\n"
            .'function emit(array $line): void { fwrite(STDOUT, json_encode($line)."\\n"); fflush(STDOUT); }'."\n"
            .$body);
        // A shebang cannot hold a PHP binary whose path contains spaces.
        file_put_contents($binary, "#!/bin/sh\nexec ".escapeshellarg(PHP_BINARY).' '.escapeshellarg($script)."\n");
        chmod($binary, 0755);

        try {
            $stream = (new ExecTransport($binary))->stream(new RunRequest(
                RunMode::Start,
                null,
                'Go.',
                new SessionOptions(sys_get_temp_dir()),
                $run,
                steering: $steering,
            ));

            return iterator_to_array($stream, false);
        } finally {
            unlink($script);
            unlink($binary);
        }
    }

    /** @param list<string> $command */
    private static function valueOf(array $command, string $flag): ?string
    {
        $position = array_search($flag, $command, true);

        return $position === false ? null : ($command[$position + 1] ?? null);
    }
}
