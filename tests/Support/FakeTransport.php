<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Support;

use Develate\ClaudecodeCli\Content\TextBlock;
use Develate\ClaudecodeCli\Message\AssistantMessage;
use Develate\ClaudecodeCli\Message\ResultMessage;
use Develate\ClaudecodeCli\Process\ProcessResult;
use Develate\ClaudecodeCli\Transport\RunMode;
use Develate\ClaudecodeCli\Transport\RunRequest;
use Develate\ClaudecodeCli\Transport\Transport;
use Develate\ClaudecodeCli\Value\Usage;

final class FakeTransport implements Transport
{
    /** @var list<RunRequest> */
    public array $requests = [];

    public function stream(RunRequest $request): \Generator
    {
        $this->requests[] = $request;
        $id = $request->mode === RunMode::Fork ? 'session-child' : ($request->sessionId ?? 'session-one');
        $assistantRaw = [
            'type' => 'assistant',
            'session_id' => $id,
            'message' => ['content' => [['type' => 'text', 'text' => 'answer: '.$request->prompt]]],
        ];
        yield new AssistantMessage(
            'claude-test',
            [new TextBlock('answer: '.$request->prompt, $assistantRaw['message']['content'][0])],
            new Usage(inputTokens: 10, outputTokens: 2),
            null,
            $id,
            'end_turn',
            $assistantRaw,
        );

        $resultRaw = [
            'type' => 'result',
            'subtype' => 'success',
            'session_id' => $id,
            'result' => 'answer: '.$request->prompt,
            'num_turns' => 1,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 2],
        ];
        yield new ResultMessage(
            'success',
            false,
            $id,
            'answer: '.$request->prompt,
            new Usage(inputTokens: 10, outputTokens: 2),
            [],
            0.01,
            1,
            'end_turn',
            'completed',
            [],
            null,
            $resultRaw,
        );

        return new ProcessResult(0, '');
    }
}
