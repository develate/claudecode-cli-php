<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tests\Value;

use Develate\ClaudecodeCli\Value\Usage;
use PHPUnit\Framework\TestCase;

final class UsageTest extends TestCase
{
    public function testConvenienceCalculationsIncludeCacheTokens(): void
    {
        $usage = new Usage(10, 5, 30, 10);

        self::assertSame(55, $usage->totalTokens());
        self::assertSame(10, $usage->uncachedInputTokens());
        self::assertSame(0.6, $usage->cacheHitRatio());
    }
}
