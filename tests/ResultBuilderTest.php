<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests;

use Develate\ClaudecodeCli\Message\ResultMessage;
use Develate\ClaudecodeCli\Parser\StreamJsonParser;
use Develate\ClaudecodeCli\ResultBuilder;
use Develate\ClaudecodeCli\Value\FileChangeKind;
use Develate\ClaudecodeCli\Value\ResultStatus;
use Develate\ClaudecodeCli\Value\RunMetadata;
use PHPUnit\Framework\TestCase;

final class ResultBuilderTest extends TestCase
{
    public function testBuildsResultAndConvenienceViews(): void
    {
        $parser = new StreamJsonParser();
        $builder = new ResultBuilder();
        $lines = [
            '{"type":"assistant","session_id":"s1","message":{"model":"claude-sonnet","content":[{"type":"thinking","thinking":"consider"},{"type":"tool_use","id":"b1","name":"Bash","input":{"command":"composer test"}},{"type":"tool_use","id":"e1","name":"Edit","input":{"file_path":"src/A.php"}}],"usage":{"input_tokens":2,"output_tokens":3}}}',
            '{"type":"user","tool_use_result":{"exitCode":0},"message":{"content":[{"type":"tool_result","tool_use_id":"b1","content":"OK"}]}}',
            '{"type":"assistant","session_id":"s1","message":{"model":"claude-sonnet","stop_reason":"end_turn","content":[{"type":"text","text":"fallback text"}]}}',
            '{"type":"result","subtype":"success","is_error":false,"session_id":"s1","result":"final text","num_turns":2,"terminal_reason":"completed","total_cost_usd":0.12,"usage":{"input_tokens":4,"cache_read_input_tokens":5,"output_tokens":6},"modelUsage":{"claude-sonnet":{"inputTokens":4,"outputTokens":6,"costUSD":0.12}},"permission_denials":[],"structured_output":{"approved":true}}',
        ];
        foreach ($lines as $line) {
            foreach ($parser->parseLine($line) as $item) {
                $builder->add($item);
            }
        }

        $result = $builder->build(new RunMetadata('2.1.0', 'sonnet', '/project'), 0);

        self::assertSame('final text', $result->text);
        self::assertSame(ResultStatus::Success, $result->status);
        self::assertSame(4, $result->usage->inputTokens);
        self::assertSame(0.12, $result->estimatedCostUsd);
        self::assertSame('composer test', $result->commands()[0]->command);
        self::assertSame('OK', $result->commands()[0]->output);
        self::assertSame(0, $result->commands()[0]->exitCode);
        self::assertSame(FileChangeKind::Edit, $result->fileChanges()[0]->kind);
        self::assertSame('consider', $result->thinking()[0]->thinking);
        self::assertSame(0.12, $result->estimatedCostForModel('claude-sonnet'));
        self::assertTrue($result->structuredOutput['approved']);
        self::assertInstanceOf(ResultMessage::class, $result->messages[array_key_last($result->messages)]);
    }

    public function testFallsBackToLastMainAssistantText(): void
    {
        $parser = new StreamJsonParser();
        $builder = new ResultBuilder();
        foreach ([
            '{"type":"assistant","session_id":"s1","message":{"content":[{"type":"text","text":"intermediate"}]}}',
            '{"type":"assistant","session_id":"s1","message":{"content":[{"type":"text","text":"final fallback"}]}}',
            '{"type":"result","subtype":"error_max_turns","is_error":true,"session_id":"s1","result":"","num_turns":3,"usage":{}}',
        ] as $line) {
            foreach ($parser->parseLine($line) as $item) {
                $builder->add($item);
            }
        }

        $result = $builder->build(new RunMetadata('2.1.0', null, '/project'), 1);

        self::assertSame('final fallback', $result->text);
        self::assertSame(ResultStatus::MaxTurns, $result->status);
    }
}
