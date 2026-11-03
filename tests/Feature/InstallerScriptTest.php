<?php

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->target = sys_get_temp_dir().'/leb-installer-'.uniqid();
    mkdir($this->target);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->target);
});

function runInstaller(string $target): Process
{
    $process = new Process([__DIR__.'/../../bin/leb-install', $target, '--link-agents']);
    $process->run();

    return $process;
}

it('links cursor skills to real folders', function () {
    expect(runInstaller($this->target)->getExitCode())->toBe(0)
        ->and(is_file($this->target.'/.cursor/skills/leb-generate-crud/SKILL.md'))->toBeTrue()
        ->and(is_file($this->target.'/.cursor/skills/laravel-engineering/SKILL.md'))->toBeTrue();
});

it('removes the old kit/stubs folder on reinstall', function () {
    mkdir($this->target.'/laravel-engineering-blueprint/kit/stubs', 0777, true);
    touch($this->target.'/laravel-engineering-blueprint/kit/stubs/model.stub');

    expect(runInstaller($this->target)->getExitCode())->toBe(0)
        ->and(is_dir($this->target.'/laravel-engineering-blueprint/kit/stubs'))->toBeFalse()
        ->and(is_file($this->target.'/laravel-engineering-blueprint/stubs/model.stub'))->toBeTrue();
});
