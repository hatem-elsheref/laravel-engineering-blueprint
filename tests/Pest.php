<?php

use Best\Blueprint\Tests\Fixtures\TestFeatures;
use Best\Blueprint\Tests\Fixtures\TestSettings;
use Best\Blueprint\Tests\SpatieTenancyTestCase;
use Best\Blueprint\Tests\SpatieTestCase;
use Best\Blueprint\Tests\TenancyTestCase;
use Best\Blueprint\Tests\TestCase;
use Illuminate\Support\Facades\File;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
pest()->extend(TenancyTestCase::class)->in('Tenancy');
pest()->extend(SpatieTestCase::class)->in('Permissions');
pest()->extend(SpatieTenancyTestCase::class)->in('PermissionsTenancy');

/** Feature/setting tables from the package's own migration stubs, on the current default connection. */
function createFlagTables(): void
{
    (require __DIR__.'/../stubs/features-migration.stub')->up();
    (require __DIR__.'/../stubs/settings-migration.stub')->up();
}

function useTestFlags(): void
{
    require_once __DIR__.'/Fixtures/TestFeatures.php';
    require_once __DIR__.'/Fixtures/TestSettings.php';
    config()->set('blueprint.features', TestFeatures::class);
    config()->set('blueprint.settings', TestSettings::class);
}

/** Every file under the folders make:module / make:crud write to (base-relative path => contents). */
function crudOutputFiles(): array
{
    $files = [];

    foreach (['app/Modules', 'database/migrations', 'database/factories', 'resources/views', 'tests/Feature/Modules'] as $root) {
        if (! is_dir(base_path($root))) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen(base_path()) + 1);
            $files[$relative] = file_get_contents($file->getPathname());
        }
    }

    ksort($files);

    return $files;
}

/**
 * Compares what was written since $before with tests/Fixtures/crud-snapshots/<name>/ (migration timestamps
 * dropped from file names). UPDATE_CRUD_SNAPSHOTS=1 rewrites the snapshot instead.
 */
function assertCrudSnapshot(string $name, array $before): void
{
    $actual = [];

    foreach (array_diff_key(crudOutputFiles(), $before) as $path => $contents) {
        $actual[preg_replace('#/\d{4}_\d{2}_\d{2}_\d{6}_#', '/', $path)] = $contents;
    }

    ksort($actual);
    $dir = __DIR__.'/Fixtures/crud-snapshots/'.$name;

    if (getenv('UPDATE_CRUD_SNAPSHOTS')) {
        File::deleteDirectory($dir);

        foreach ($actual as $path => $contents) {
            File::ensureDirectoryExists(dirname("{$dir}/{$path}"));
            file_put_contents("{$dir}/{$path}", $contents);
        }
    }

    $expected = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $expected[substr($file->getPathname(), strlen($dir) + 1)] = file_get_contents($file->getPathname());
    }

    ksort($expected);

    expect(array_keys($actual))->toBe(array_keys($expected));

    foreach ($expected as $path => $contents) {
        expect($actual[$path])->toBe($contents, $path);
    }
}
