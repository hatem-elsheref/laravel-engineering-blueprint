<?php

declare(strict_types=1);

namespace Best\Blueprint\Architecture;

final readonly class ModuleBoundaryViolation
{
    public function __construct(
        public string $file,
        public int $line,
        public string $ownerModule,
        public string $otherModule,
        public string $import,
        public string $reason,
    ) {}

    public function message(): string
    {
        return "{$this->file}:{$this->line} [{$this->ownerModule}] must not import {$this->import} from [{$this->otherModule}] ({$this->reason})";
    }
}
