<?php

namespace Best\Blueprint\Support;

final readonly class CompatibilityResult
{
    /**
     * @param  list<array{name: string, required: string, actual: string, passed: bool}>  $checks
     */
    public function __construct(
        public bool $passed,
        public array $checks,
    ) {}

    /**
     * @return list<array{name: string, required: string, actual: string, passed: bool}>
     */
    public function failed(): array
    {
        return array_values(array_filter($this->checks, fn (array $check) => ! $check['passed']));
    }
}
