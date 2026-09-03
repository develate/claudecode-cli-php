<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\Event\ThinkingDelta;
use Develate\ClaudecodeCli\Exception\ProcessCancelled;
use Develate\ClaudecodeCli\Exception\ProcessFailed;
use Develate\ClaudecodeCli\Session;
use Develate\ClaudecodeCli\SessionOptions;
use Develate\ClaudecodeCli\Tests\Support\ScriptedTransport;
use Develate\ClaudecodeCli\Value\ResultStatus;
use PHPUnit\Framework\TestCase;

final class RunTest extends TestCase
{
    public function testCarriesInitMetadataThinkingAndSubagentOutputThrough(): void
    {
        $result = $this->session(new ScriptedTransport([
            [
                'type' => 'system',
                'subtype' => 'init',
                'session_id' => 'session-one',
                'model' => 'claude-sonnet-5',
                'cwd' => '/project',
                'tools' => ['Read', 'Task'],
                'mcp_servers' => [['name' => 'develate_plan', 'status' => 'connected']],
            ],
            [
                'type' => 'stream_event',
                'event' => ['type' => 'content_block_delta', 'delta' => ['type' => 'thinking_delta', 'thinking' => 'planning']],
            ],
            [
                'type' => 'assistant',
                'session_id' => 'session-one',
                'parent_tool_use_id' => 'toolu_task',
                'message' => ['model' => 'claude-haiku-4-5', 'content' => [['type' => 'text', 'text' => 'subagent says hi']]],
            ],
            [
                'type' => 'assistant',
                'session_id' => 'session-one',
                'message' => [
                    'model' => 'claude-sonnet-5',
                    'content' => [
                        ['type' => 'thinking', 'thinking' => 'settled'],
                        ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'Bash', 'input' => ['command' => 'ls']],
                        ['type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'Edit', 'input' => ['file_path' => '/project/app.php']],
                    ],
                ],
            ],
            [
                'type' => 'user',
                'session_id' => 'session-one',
                'message' => ['content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => 'app.php']]],
            ],
            ['type' => 'a_type_this_sdk_has_never_seen', 'payload' => ['anything' => true]],
            [
                'type' => 'result',
                'subtype' => 'success',
                'session_id' => 'session-one',
                'result' => 'done',
                'num_turns' => 2,
                'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
                'total_cost_usd' => 0.42,
            ],
        ]))->query('go');

        self::assertSame('done', $result->text);
        self::assertSame(ResultStatus::Success, $result->status);
        self::assertSame('claude-sonnet-5', $result->init()?->model);
        self::assertSame(['Read', 'Task'], $result->init()?->tools);
        self::assertCount(1, $result->thinkingDeltas());
        self::assertSame('planning', $result->thinkingDeltas()[0]->thinking);
        self::assertSame('settled', $result->thinking()[0]->thinking);
        self::assertCount(1, $result->subagentMessages());
        self::assertSame('subagent says hi', $result->subagentMessages()[0]->text());
        self::assertSame('ls', $result->commands()[0]->command);
        self::assertSame('app.php', $result->commands()[0]->output);
        self::assertSame('/project/app.php', $result->fileChanges()[0]->path);
        self::assertSame(0.42, $result->estimatedCostUsd);
    }

    public function testUnknownTopLevelTypesSurviveAsEvents(): void
    {
        $result = $this->session(new ScriptedTransport([
            ['type' => 'quantum_entanglement_event', 'spin' => 'up'],
            ['type' => 'result', 'subtype' => 'success', 'session_id' => 's', 'result' => 'ok'],
        ]))->query('go');

        self::assertSame('ok', $result->text);
        self::assertSame('quantum_entanglement_event', $result->events[0]->type);
        self::assertSame(['type' => 'quantum_entanglement_event', 'spin' => 'up'], $result->events[0]->raw());
    }

    public function testExpectedTerminalResultsAreResultsRatherThanExceptions(): void
    {
        $result = $this->session(new ScriptedTransport([
            ['type' => 'result', 'subtype' => 'error_max_turns', 'is_error' => true, 'session_id' => 's', 'num_turns' => 10],
        ], exitCode: 1))->query('go');

        self::assertSame('max_turns', $result->terminalReason);
        self::assertSame(1, $result->exitCode);
    }

    public function testANonZeroExitWithoutAResultMessageFails(): void
    {
        $this->expectException(ProcessFailed::class);

        $this->session(new ScriptedTransport([], exitCode: 2, stderr: 'boom'))->query('go');
    }

    public function testCancellingStopsTheStreamAndLeavesNoResult(): void
    {
        $transport = new ScriptedTransport([
            ['type' => 'assistant', 'session_id' => 's', 'message' => ['content' => [['type' => 'text', 'text' => 'first']]]],
            ['type' => 'assistant', 'session_id' => 's', 'message' => ['content' => [['type' => 'text', 'text' => 'second']]]],
            ['type' => 'result', 'subtype' => 'success', 'session_id' => 's', 'result' => 'done'],
        ]);

        $run = $this->session($transport)->stream('go');
        $seen = [];

        try {
            foreach ($run as $item) {
                $seen[] = $item;
                $run->cancel();
            }
            self::fail('The cancelled run should have thrown.');
        } catch (ProcessCancelled) {
            // Expected: the process is stopped rather than allowed to finish.
        }

        self::assertCount(1, $seen);
        self::assertFalse($run->isComplete());
    }

    private function session(ScriptedTransport $transport): Session
    {
        return new Session($transport, '2.1.259', new SessionOptions('/project'));
    }
}
