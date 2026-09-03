<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Process;

use Develate\ClaudecodeCli\Exception\ProcessCancelled;
use Develate\ClaudecodeCli\Exception\ProcessTimedOut;
use Develate\ClaudecodeCli\Process\ClaudeProcess;
use PHPUnit\Framework\TestCase;

final class ClaudeProcessTest extends TestCase
{
    public function testStreamsLinesAndCapturesStderr(): void
    {
        $process = new ClaudeProcess([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, "{\\"type\\":\\"one\\"}\\n{\\"type\\":\\"two\\"}"); fwrite(STDERR, "warning");',
        ]);

        $lines = $process->lines();
        self::assertSame(
            ['{"type":"one"}', '{"type":"two"}'],
            iterator_to_array($lines, false),
        );
        self::assertSame(0, $lines->getReturn()->exitCode);
        self::assertSame('warning', $lines->getReturn()->stderr);
    }

    public function testCanBeCancelled(): void
    {
        $process = new ClaudeProcess([PHP_BINARY, '-r', 'usleep(100000);']);

        $this->expectException(ProcessCancelled::class);
        iterator_to_array($process->lines(static fn (): bool => true));
    }

    public function testEnforcesTimeout(): void
    {
        $process = new ClaudeProcess([PHP_BINARY, '-r', 'usleep(200000);'], timeout: 0.01);

        $this->expectException(ProcessTimedOut::class);
        iterator_to_array($process->lines());
    }
}
