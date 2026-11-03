<?php

use Best\Blueprint\Support\ConfigWriter;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\ProfileWrite;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/leb-config-writer-'.uniqid();
    $this->path = $this->dir.'/config/blueprint.php';
    $this->writer = new ConfigWriter(new Filesystem);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->dir);
});

function writeConfig(string $path, string $profileLine): void
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, "<?php\n\nreturn [\n{$profileLine}    'module_path' => 'app/Modules',\n];\n");
}

it('publishes the package config', function () {
    $this->writer->publish($this->path);

    expect(file_get_contents($this->path))->toBe(file_get_contents(__DIR__.'/../../config/blueprint.php'));
});

it('sets profile on a fresh publish', function () {
    $this->writer->publish($this->path);

    $result = $this->writer->setProfile($this->path, Profile::Saas);

    expect($result->status)->toBe(ProfileWrite::UPDATED)
        ->and($result->old)->toBeNull()
        ->and(file_get_contents($this->path))->toContain("'profile' => 'saas',");
});

it('keeps other edits', function () {
    $this->writer->publish($this->path);
    file_put_contents($this->path, str_replace("'app/Modules'", "'src/Modules'", file_get_contents($this->path)));

    $this->writer->setProfile($this->path, Profile::Modular);

    expect(file_get_contents($this->path))->toContain("'src/Modules'")->toContain("'profile' => 'modular',");
});

it('reports unchanged', function () {
    writeConfig($this->path, "    'profile' => 'saas',\n");
    $before = file_get_contents($this->path);

    $result = $this->writer->setProfile($this->path, Profile::Saas);

    expect($result->status)->toBe(ProfileWrite::UNCHANGED)
        ->and(file_get_contents($this->path))->toBe($before);
});

it('reports old value', function () {
    writeConfig($this->path, "    'profile' => \"modular\",\n");

    $result = $this->writer->setProfile($this->path, Profile::Saas);

    expect($result->status)->toBe(ProfileWrite::UPDATED)->and($result->old)->toBe('modular');
});

it('keeps trailing comment', function () {
    writeConfig($this->path, "    'profile' => 'saas', // set by install\n");

    $this->writer->setProfile($this->path, Profile::Basic);

    expect(file_get_contents($this->path))->toContain("    'profile' => 'basic', // set by install\n");
});

it('refuses unrecordable configs', function (string $profileLine) {
    writeConfig($this->path, $profileLine);
    $before = file_get_contents($this->path);

    $result = $this->writer->setProfile($this->path, Profile::Saas);

    expect($result->status)->toBe(ProfileWrite::UNRECORDABLE)
        ->and(file_get_contents($this->path))->toBe($before);
})->with([
    'missing key' => [''],
    'env expression' => ["    'profile' => env('BLUEPRINT_PROFILE', 'saas'),\n"],
    'duplicate profile keys' => ["    'profile' => 'basic',\n    'profile' => 'modular',\n"],
    'nested profile key' => ["    'nested' => [\n        'profile' => 'x',\n    ],\n"],
]);

it('refuses interpolated strings', function () {
    writeConfig($this->path, "    'profile' => \"\$x\",\n");
    $before = file_get_contents($this->path);

    expect($this->writer->setProfile($this->path, Profile::Saas)->status)->toBe(ProfileWrite::UNRECORDABLE)
        ->and(file_get_contents($this->path))->toBe($before);
});

it('ignores profile lines inside block comments', function () {
    writeConfig($this->path, "    /*\n    'profile' => 'basic',\n    */\n    'profile' => 'modular',\n");

    $result = $this->writer->setProfile($this->path, Profile::Saas);

    expect($result->status)->toBe(ProfileWrite::UPDATED)
        ->and($result->old)->toBe('modular')
        ->and(file_get_contents($this->path))->toContain("    /*\n    'profile' => 'basic',\n    */\n    'profile' => 'saas',\n");
});

it('does not rewrite a commented line when the real one is an expression', function () {
    writeConfig($this->path, "    /* 'profile' => 'basic', */\n    'profile' => env('BLUEPRINT_PROFILE'),\n");
    $before = file_get_contents($this->path);

    expect($this->writer->setProfile($this->path, Profile::Saas)->status)->toBe(ProfileWrite::UNRECORDABLE)
        ->and(file_get_contents($this->path))->toBe($before);
});

it('reads the recorded profile', function () {
    writeConfig($this->path, "    'profile' => 'saas',\n");
    expect($this->writer->recordedProfile($this->path))->toBe('saas');

    writeConfig($this->path, "    'profile' => env('X'),\n");
    expect($this->writer->recordedProfile($this->path))->toBeNull()
        ->and($this->writer->recordedProfile($this->dir.'/missing.php'))->toBeNull();
});
