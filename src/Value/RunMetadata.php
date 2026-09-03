<?php

declare(strict_types=1);

namespace Develate\ClaudecodeCli\Value;

final readonly class RunMetadata
{
    /** @param list<string> $reportedModels */
    public function __construct(
        public string $claudeVersion,
        public ?string $requestedModel,
        public string $cwd,
        public array $reportedModels = [],
    ) {
    }
}
