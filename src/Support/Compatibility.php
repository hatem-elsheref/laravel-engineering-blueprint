<?php

namespace Best\Blueprint\Support;

use Illuminate\Foundation\Application;

class Compatibility
{
    public function __construct(
        private readonly string $minPhp = '8.3',
        private readonly string $minLaravel = '12',
    ) {}

    public function check(?string $phpVersion = null, ?string $laravelVersion = null): CompatibilityResult
    {
        $checks = [
            $this->compare('php', $this->minPhp, $phpVersion ?? PHP_VERSION),
            $this->compare('laravel', $this->minLaravel, $laravelVersion ?? Application::VERSION),
        ];

        $passed = array_reduce($checks, fn (bool $carry, array $check) => $carry && $check['passed'], true);

        return new CompatibilityResult($passed, $checks);
    }

    /**
     * @return array{name: string, required: string, actual: string, passed: bool}
     */
    private function compare(string $name, string $required, string $actual): array
    {
        return [
            'name' => $name,
            'required' => $required,
            'actual' => $actual,
            'passed' => version_compare($this->numeric($actual), $required, '>='),
        ];
    }

    /**
     * Strip pre-release suffixes ("8.3.0-dev", "12.0.0-beta1") down to the numeric version.
     */
    private function numeric(string $version): string
    {
        return preg_match('/^\d+(\.\d+)*/', $version, $matches) ? $matches[0] : $version;
    }
}
