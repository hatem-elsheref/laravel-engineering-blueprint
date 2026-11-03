<?php

declare(strict_types=1);

namespace Best\Blueprint\Architecture;

/**
 * Shared rules for cross-module imports (CLI scanner and PHPStan).
 */
final class ModuleBoundaryPolicy
{
    /**
     * @param  list<string>  $forbiddenSegments
     * @param  list<string>  $allowedSegments
     */
    public function __construct(
        private readonly string $moduleNamespacePrefix,
        private readonly string $modulesPathContains,
        private readonly array $forbiddenSegments,
        private readonly array $allowedSegments,
    ) {}

    /**
     * @param  array{forbidden_cross_module_segments: list<string>, allowed_cross_module_segments: list<string>}  $architecture
     */
    public static function fromBlueprintConfig(
        string $moduleNamespacePrefix,
        string $modulesPathContains,
        array $architecture,
    ): self {
        return new self(
            rtrim($moduleNamespacePrefix, '\\').'\\',
            str_replace('\\', '/', $modulesPathContains),
            $architecture['forbidden_cross_module_segments'],
            $architecture['allowed_cross_module_segments'],
        );
    }

    public function ownerModuleFromAbsolutePath(string $absolutePath): ?string
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $needle = $this->modulesPathContains.'/';

        if (! str_contains($normalized, $needle)) {
            return null;
        }

        $rest = substr($normalized, (int) strpos($normalized, $needle) + strlen($needle));
        $parts = explode('/', $rest, 2);

        return $parts[0] !== '' ? $parts[0] : null;
    }

    public function ownerModuleFromModuleRootRelativePath(string $relativePath): ?string
    {
        $parts = explode('/', str_replace('\\', '/', ltrim($relativePath, '/')), 2);

        return $parts[0] !== '' ? $parts[0] : null;
    }

    /**
     * @return non-empty-string|null Human-readable violation reason.
     */
    public function violationReason(string $ownerModule, string $targetFqn): ?string
    {
        $prefix = $this->moduleNamespacePrefix;

        if (! str_starts_with($targetFqn, $prefix)) {
            return null;
        }

        $otherModule = $this->moduleNameFromFqn($targetFqn);

        if ($otherModule === null || strcasecmp($otherModule, $ownerModule) === 0) {
            return null;
        }

        $rest = substr($targetFqn, strlen($prefix.$otherModule.'\\'));
        $pieces = explode('\\', $rest);
        $segment = $pieces[0];

        if ($segment === '' || in_array($segment, $this->allowedSegments, true)) {
            return null;
        }

        if (in_array($segment, $this->forbiddenSegments, true)) {
            return "Cross-module {$segment} reference to {$targetFqn} is not allowed; use Shared contracts, public Actions/Queries, or Events.";
        }

        return null;
    }

    public function moduleNamespacePrefix(): string
    {
        return $this->moduleNamespacePrefix;
    }

    private function moduleNameFromFqn(string $fqn): ?string
    {
        $prefix = $this->moduleNamespacePrefix;

        if (! str_starts_with($fqn, $prefix)) {
            return null;
        }

        $rest = substr($fqn, strlen($prefix));
        $pieces = explode('\\', $rest, 2);

        return $pieces[0] !== '' ? $pieces[0] : null;
    }
}
