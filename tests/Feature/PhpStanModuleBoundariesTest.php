<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function phpstanBoundaryAnalyse(string $relativeFile): Process
{
    $packageRoot = dirname(__DIR__, 2);
    $config = $packageRoot.'/tests/Fixtures/module-boundaries/phpstan.neon';
    $target = $packageRoot.'/tests/Fixtures/module-boundaries/'.$relativeFile;

    $process = new Process(
        [PHP_BINARY, $packageRoot.'/vendor/bin/phpstan', 'analyse', '--no-progress', '-c', $config, $target],
        $packageRoot,
        null,
        null,
        120,
    );
    $process->run();

    return $process;
}

it('reports cross-module static references via the phpstan extension', function (): void {
    $process = phpstanBoundaryAnalyse('app/Modules/Orders/Actions/CrossModuleStatic.php');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput().$process->getErrorOutput())->toContain('blueprint.moduleBoundary')
        ->and($process->getOutput().$process->getErrorOutput())->toContain('Inventory\\Models\\Product');
});

it('allows cross-module action imports in phpstan', function (): void {
    $process = phpstanBoundaryAnalyse('app/Modules/Orders/Actions/AllowedActionImport.php');

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
});
