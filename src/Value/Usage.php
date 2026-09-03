<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadInputTokens = 0,
        public int $cacheCreationInputTokens = 0,
    ) {
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            inputTokens: self::integer($data, 'input_tokens', 'inputTokens'),
            outputTokens: self::integer($data, 'output_tokens', 'outputTokens'),
            cacheReadInputTokens: self::integer($data, 'cache_read_input_tokens', 'cacheReadInputTokens'),
            cacheCreationInputTokens: self::integer($data, 'cache_creation_input_tokens', 'cacheCreationInputTokens'),
        );
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->cacheReadInputTokens + $this->cacheCreationInputTokens + $this->outputTokens;
    }

    public function uncachedInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function cacheHitRatio(): float
    {
        $input = $this->inputTokens + $this->cacheReadInputTokens + $this->cacheCreationInputTokens;

        return $input === 0 ? 0.0 : $this->cacheReadInputTokens / $input;
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $snake, string $camel): int
    {
        $value = $data[$snake] ?? $data[$camel] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
