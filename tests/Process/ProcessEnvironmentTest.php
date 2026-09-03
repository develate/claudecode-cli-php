<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Process;

use Develate\ClaudecodeCli\Process\ClaudeProcess;
use PHPUnit\Framework\TestCase;

/**
 * The environment is how a host keeps two Claude accounts apart, so it has to
 * be exact: what is set must arrive, and what is removed must not be inherited.
 */
final class ProcessEnvironmentTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['ANTHROPIC_API_KEY'], $_SERVER['CLAUDE_INHERITED']);
        putenv('ANTHROPIC_API_KEY');
        putenv('CLAUDE_INHERITED');
    }

    public function testMergesTheGivenEnvironmentOntoTheInheritedOne(): void
    {
        putenv('CLAUDE_INHERITED=kept');
        $_SERVER['CLAUDE_INHERITED'] = 'kept';

        $process = new ClaudeProcess(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode(["dir" => getenv("CLAUDE_CONFIG_DIR"), "inherited" => getenv("CLAUDE_INHERITED")])."\n");'],
            env: ['CLAUDE_CONFIG_DIR' => '/accounts/one'],
        );

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(['dir' => '/accounts/one', 'inherited' => 'kept'], json_decode($lines[0], true));
    }

    public function testFalseRemovesAnInheritedVariable(): void
    {
        putenv('ANTHROPIC_API_KEY=sk-inherited');
        $_SERVER['ANTHROPIC_API_KEY'] = 'sk-inherited';

        $process = new ClaudeProcess(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode(["key" => getenv("ANTHROPIC_API_KEY")])."\n");'],
            env: ['ANTHROPIC_API_KEY' => false],
        );

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(['key' => false], json_decode($lines[0], true));
    }

    public function testWritesThePromptToStdin(): void
    {
        $process = new ClaudeProcess(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, json_encode(["prompt" => stream_get_contents(STDIN)])."\n");'],
            input: 'Explain this repository.',
        );

        $lines = iterator_to_array($process->lines(), false);

        self::assertSame(['prompt' => 'Explain this repository.'], json_decode($lines[0], true));
    }
}
