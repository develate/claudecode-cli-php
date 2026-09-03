<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Tool;

use Develate\ClaudecodeCli\StreamItem;

final readonly class ToolResult implements StreamItem
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $toolUseId,
        public mixed $content,
        public bool $isError,
        private array $raw,
    ) {
    }

    public function output(): ?string
    {
        if (is_string($this->content)) {
            return $this->content;
        }
        if ($this->content === null) {
            return null;
        }

        try {
            return json_encode($this->content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return null;
        }
    }

    public function exitCode(): ?int
    {
        return $this->findInteger($this->raw, ['exit_code', 'exitCode', 'code']);
    }

    public function raw(): array
    {
        return $this->raw;
    }

    /** @param array<string, mixed> $data @param list<string> $keys */
    private function findInteger(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (int) $data[$key];
            }
        }
        foreach ($data as $value) {
            if (is_array($value) && ($found = $this->findInteger($value, $keys)) !== null) {
                return $found;
            }
        }

        return null;
    }
}
