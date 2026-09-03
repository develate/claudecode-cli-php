<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Process;

final class StreamJsonReader
{
    private string $buffer = '';

    /** @return list<string> */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;
        $lines = preg_split('/\r?\n/', $this->buffer) ?: [];
        $this->buffer = array_pop($lines) ?? '';

        return array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
    }

    public function finish(): ?string
    {
        $line = trim($this->buffer);
        $this->buffer = '';

        return $line === '' ? null : $line;
    }
}
