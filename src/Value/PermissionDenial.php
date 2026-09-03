<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class PermissionDenial
{
    /** @param array<string, mixed> $input @param array<string, mixed> $raw */
    public function __construct(
        public ?string $toolUseId,
        public ?string $toolName,
        public array $input,
        private array $raw,
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $input = $raw['tool_input'] ?? $raw['input'] ?? [];

        return new self(
            self::string($raw['tool_use_id'] ?? $raw['toolUseId'] ?? null),
            self::string($raw['tool_name'] ?? $raw['toolName'] ?? null),
            is_array($input) ? $input : [],
            $raw,
        );
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->raw;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
