<?php

declare(strict_types=1);

namespace Best\Blueprint\Architecture;

use Best\Blueprint\Support\Modules;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds cross-module PHP use statements that break MODULE-BOUNDARIES.md (HTTP layer, models, etc.).
 */
final class ModuleBoundaryScanner
{
    public function __construct(
        private readonly Modules $modules,
        private readonly ModuleBoundaryPolicy $policy,
    ) {}

    /**
     * @return list<ModuleBoundaryViolation>
     */
    public function scan(?string $root = null): array
    {
        $root ??= base_path($this->modules->path());

        if (! is_dir($root)) {
            return [];
        }

        $violations = [];

        foreach ($this->phpFiles($root) as $file) {
            $owner = $this->policy->ownerModuleFromModuleRootRelativePath(
                substr($file->getPathname(), strlen(rtrim($root, '/\\')) + 1),
            );

            if ($owner === null) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            foreach ($this->imports($contents) as $line => $import) {
                if (($reason = $this->policy->violationReason($owner, $import)) !== null) {
                    $other = $this->otherModuleFromFqn($import);

                    $violations[] = new ModuleBoundaryViolation(
                        $this->relativePath($file->getPathname()),
                        $line,
                        $owner,
                        $other ?? '?',
                        $import,
                        $reason,
                    );
                }
            }
        }

        usort($violations, fn (ModuleBoundaryViolation $a, ModuleBoundaryViolation $b) => [$a->file, $a->line] <=> [$b->file, $b->line]);

        return $violations;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFiles(string $root): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return array<int, string> line => FQCN
     */
    private function imports(string $contents): array
    {
        $imports = [];

        if (preg_match_all('/^\s*use\s+([^;]+);/m', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        foreach ($matches[1] as [$statement, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

            foreach (explode(',', $statement) as $part) {
                $part = trim($part);
                $part = preg_replace('/\s+as\s+\w+$/i', '', $part) ?? $part;
                $part = ltrim($part, '\\');

                if ($part !== '') {
                    $imports[$line] = $part;
                }
            }
        }

        return $imports;
    }

    private function otherModuleFromFqn(string $fqn): ?string
    {
        $prefix = $this->policy->moduleNamespacePrefix();

        if (! str_starts_with($fqn, $prefix)) {
            return null;
        }

        $rest = substr($fqn, strlen($prefix));
        $pieces = explode('\\', $rest, 2);

        return $pieces[0] !== '' ? $pieces[0] : null;
    }

    private function relativePath(string $path): string
    {
        $base = base_path();

        return str_starts_with($path, $base.'/') ? substr($path, strlen($base) + 1) : $path;
    }
}
