<?php

declare(strict_types=1);

use Best\Blueprint\Architecture\ModuleBoundaryScanner;
use Best\Blueprint\Architecture\ModuleBoundaryViolation;
use Best\Blueprint\PhpStan\ModuleBoundaryPolicyFactory;
use Best\Blueprint\Support\Modules;
use Illuminate\Support\Facades\File;

const MODULE_GRAPH_FIXTURE = __DIR__.'/../Fixtures/module-boundaries/app/Modules';

function moduleGraphScanner(): ModuleBoundaryScanner
{
    return new ModuleBoundaryScanner(new Modules, ModuleBoundaryPolicyFactory::fromApplication(new Modules));
}

/**
 * @return list<ModuleBoundaryViolation>
 */
function scanModuleGraphFixture(): array
{
    return moduleGraphScanner()->scan(MODULE_GRAPH_FIXTURE);
}

/**
 * @return array<int, string> line => FQCN
 */
function parseUseImports(string $contents): array
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

it('matches the golden forbidden cross-module use imports in the fixture tree', function (): void {
    $violations = scanModuleGraphFixture();

    expect($violations)->toHaveCount(2)
        ->and(collect($violations)->pluck('import')->unique()->values()->all())
        ->toBe(['App\Modules\Inventory\Models\Product'])
        ->and(collect($violations)->pluck('ownerModule')->unique()->all())->toBe(['Orders'])
        ->and(collect($violations)->pluck('otherModule')->unique()->all())->toBe(['Inventory']);

    $files = collect($violations)->map(fn (ModuleBoundaryViolation $v) => basename($v->file))->sort()->values()->all();

    expect($files)->toBe(['CrossModuleStatic.php', 'CrossModuleUse.php']);
});

it('allows cross-module action imports in the golden fixture tree', function (): void {
    $policy = ModuleBoundaryPolicyFactory::fromApplication(new Modules);
    $path = MODULE_GRAPH_FIXTURE.'/Orders/Actions/AllowedActionImport.php';
    $imports = parseUseImports((string) file_get_contents($path));

    expect($imports)->toHaveCount(1)
        ->and($policy->violationReason('Orders', reset($imports)))->toBeNull();
});

it('classifies every cross-module use in the fixture against config segment lists', function (): void {
    $policy = ModuleBoundaryPolicyFactory::fromApplication(new Modules);
    $allowed = config('blueprint.architecture.allowed_cross_module_segments');
    $forbidden = config('blueprint.architecture.forbidden_cross_module_segments');
    $prefix = $policy->moduleNamespacePrefix();

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MODULE_GRAPH_FIXTURE, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen(MODULE_GRAPH_FIXTURE) + 1);
        $owner = $policy->ownerModuleFromModuleRootRelativePath($relative);

        if ($owner === null) {
            continue;
        }

        foreach (parseUseImports((string) file_get_contents($file->getPathname())) as $import) {
            if (! str_starts_with($import, $prefix)) {
                continue;
            }

            $other = explode('\\', substr($import, strlen($prefix)), 2)[0] ?? '';

            if (strcasecmp($other, $owner) === 0) {
                continue;
            }

            $segment = explode('\\', substr($import, strlen($prefix.$other.'\\')), 2)[0] ?? '';
            $reason = $policy->violationReason($owner, $import);

            if ($reason !== null) {
                expect($segment)->toBeIn($forbidden);

                continue;
            }

            expect($segment)->toBeIn($allowed);
        }
    }
});

it('runs engineering:architecture clean against a modular app copy of allowed imports only', function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $root = base_path('app/Modules');
    File::deleteDirectory($root);

    File::ensureDirectoryExists($root.'/Orders/Actions');
    File::copy(
        MODULE_GRAPH_FIXTURE.'/Orders/Actions/AllowedActionImport.php',
        $root.'/Orders/Actions/AllowedActionImport.php',
    );
    File::ensureDirectoryExists($root.'/Inventory/Actions');
    File::copy(
        MODULE_GRAPH_FIXTURE.'/Inventory/Actions/ReserveStock.php',
        $root.'/Inventory/Actions/ReserveStock.php',
    );

    $this->artisan('engineering:architecture')
        ->expectsOutputToContain('No module boundary violations')
        ->assertExitCode(0);

    File::deleteDirectory($root);
});

it('runs engineering:architecture and fails on a forbidden cross-module model import', function (): void {
    config()->set('blueprint.profile', 'modular');
    $root = base_path('app/Modules');
    File::deleteDirectory($root);

    File::ensureDirectoryExists($root.'/Orders/Actions');
    File::copy(
        MODULE_GRAPH_FIXTURE.'/Orders/Actions/CrossModuleUse.php',
        $root.'/Orders/Actions/CrossModuleUse.php',
    );

    $this->artisan('engineering:architecture')
        ->expectsOutputToContain('[Orders] must not import')
        ->assertExitCode(1);

    File::deleteDirectory($root);
});
