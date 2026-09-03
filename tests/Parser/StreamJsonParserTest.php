<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Parser;

use Develate\ClaudecodeCli\Content\TextBlock;
use Develate\ClaudecodeCli\Content\UnknownBlock;
use Develate\ClaudecodeCli\Event\ApiRetryEvent;
use Develate\ClaudecodeCli\Event\TextDelta;
use Develate\ClaudecodeCli\Event\UnknownEvent;
use Develate\ClaudecodeCli\Exception\InvalidStreamJson;
use Develate\ClaudecodeCli\Message\AssistantMessage;
use Develate\ClaudecodeCli\Message\UnknownMessage;
use Develate\ClaudecodeCli\Parser\StreamJsonParser;
use Develate\ClaudecodeCli\Tool\ToolResult;
use Develate\ClaudecodeCli\Tool\ToolUse;
use PHPUnit\Framework\TestCase;

final class StreamJsonParserTest extends TestCase
{
    public function testParsesAssistantContentAndExpandsToolUse(): void
    {
        $items = (new StreamJsonParser())->parseLine(json_encode([
            'type' => 'assistant',
            'future' => true,
            'session_id' => 'session-1',
            'parent_tool_use_id' => 'toolu-parent',
            'message' => [
                'model' => 'claude-sonnet-test',
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 3, 'output_tokens' => 2],
                'content' => [
                    ['type' => 'text', 'text' => 'Running '],
                    ['type' => 'tool_use', 'id' => 'toolu-1', 'name' => 'Bash', 'input' => ['command' => 'composer test']],
                    ['type' => 'future_block', 'new_field' => 42],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertCount(2, $items);
        self::assertInstanceOf(AssistantMessage::class, $items[0]);
        self::assertSame('Running ', $items[0]->text());
        self::assertInstanceOf(TextBlock::class, $items[0]->content[0]);
        self::assertInstanceOf(UnknownBlock::class, $items[0]->content[2]);
        self::assertTrue($items[0]->raw()['future']);
        self::assertInstanceOf(ToolUse::class, $items[1]);
        self::assertSame('toolu-parent', $items[1]->parentToolUseId);
    }

    public function testParsesTextDelta(): void
    {
        $items = (new StreamJsonParser())->parseLine(
            '{"type":"stream_event","parent_tool_use_id":null,"event":{"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hi"}}}',
        );

        self::assertInstanceOf(TextDelta::class, $items[0]);
        self::assertSame('Hi', $items[0]->text);
    }

    public function testParsesToolResultFromUserMessage(): void
    {
        $items = (new StreamJsonParser())->parseLine(
            '{"type":"user","tool_use_result":{"exitCode":0},"message":{"role":"user","content":[{"type":"tool_result","tool_use_id":"toolu-1","content":"ok","is_error":false}]}}',
        );

        self::assertCount(2, $items);
        self::assertInstanceOf(ToolResult::class, $items[1]);
        self::assertSame('ok', $items[1]->output());
        self::assertSame(0, $items[1]->exitCode());
    }

    public function testKeepsUnknownMessagesEventsAndBlocks(): void
    {
        $parser = new StreamJsonParser();
        $message = $parser->parseLine('{"type":"future_message","message":{"anything":true}}')[0];
        $event = $parser->parseLine('{"type":"future_event","anything":true}')[0];

        self::assertInstanceOf(UnknownMessage::class, $message);
        self::assertInstanceOf(UnknownEvent::class, $event);
        self::assertTrue($event->raw()['anything']);
    }

    public function testMapsApiRetrySystemRuntimeMessageToEvent(): void
    {
        $item = (new StreamJsonParser())->parseLine(
            '{"type":"system","subtype":"api_retry","attempt":2,"delay_ms":500,"error":"overloaded"}',
        )[0];

        self::assertInstanceOf(ApiRetryEvent::class, $item);
        self::assertSame(2, $item->attempt);
        self::assertSame(500, $item->delayMs);
    }

    public function testRejectsMalformedJsonOnly(): void
    {
        $this->expectException(InvalidStreamJson::class);
        (new StreamJsonParser())->parseLine('{not json');
    }
}
