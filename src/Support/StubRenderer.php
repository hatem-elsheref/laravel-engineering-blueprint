<?php

namespace Best\Blueprint\Support;

use Illuminate\Filesystem\Filesystem;

class StubRenderer
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Resolve a stub: configured stub_path, then published stubs/blueprint, then the package.
     */
    public function path(string $stub): string
    {
        $candidates = [];
        $override = config('blueprint.stub_path');

        if (is_string($override) && $override !== '') {
            $candidates[] = rtrim($override, '/').'/'.$stub;
        }

        $candidates[] = base_path('stubs/blueprint/'.$stub);
        $candidates[] = realpath(__DIR__.'/../../stubs').'/'.$stub;

        foreach ($candidates as $candidate) {
            if ($this->files->isFile($candidate)) {
                return $candidate;
            }
        }

        throw new StubException("Stub {$stub} not found.");
    }

    /**
     * @param  array<string, string>  $values
     */
    public function render(string $stub, array $values): string
    {
        $path = $this->path($stub);
        $contents = $this->files->get($path);

        foreach ($values as $key => $value) {
            $contents = str_replace('{{ '.$key.' }}', $value, $contents);
        }

        if (preg_match('/\{\{\s*[\w.]+\s*\}\}/', $contents, $match)) {
            $reason = 'unknown placeholder '.$match[0];

            throw new StubException("Stub {$path} has {$reason}.", $path, $reason);
        }

        return $contents;
    }
}
