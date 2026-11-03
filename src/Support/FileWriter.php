<?php

namespace Best\Blueprint\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

/**
 * Writes a set of new files; if any write fails, removes everything it created.
 */
final class FileWriter
{
    /** @var list<string> */
    private array $written = [];

    /** @var list<string> */
    private array $createdDirectories = [];

    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  array<string, string>  $contents  absolute path => contents
     *
     * @throws Throwable after rolling back
     */
    public function write(array $contents): void
    {
        try {
            foreach ($contents as $path => $body) {
                $this->makeDirectory(dirname($path));
                $this->files->put($path, $body);
                $this->written[] = $path;
            }
        } catch (Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    public function rollback(): void
    {
        foreach (array_reverse($this->written) as $path) {
            @unlink($path);
        }

        foreach (array_reverse($this->createdDirectories) as $directory) {
            @rmdir($directory);
        }

        $this->written = [];
        $this->createdDirectories = [];
    }

    private function makeDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        $this->makeDirectory(dirname($directory));

        if (! @mkdir($directory) && ! is_dir($directory)) {
            throw new RuntimeException("cannot create {$directory}");
        }

        $this->createdDirectories[] = $directory;
    }
}
