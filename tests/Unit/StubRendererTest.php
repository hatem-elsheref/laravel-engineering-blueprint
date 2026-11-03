<?php

use Best\Blueprint\Support\StubException;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->files = new Filesystem;
    $this->renderer = new StubRenderer($this->files);
    $this->tmp = sys_get_temp_dir().'/leb-stubs-'.uniqid();
    $this->files->ensureDirectoryExists($this->tmp);
});

afterEach(function () {
    $this->files->deleteDirectory($this->tmp);
    $this->files->deleteDirectory(base_path('stubs'));
    config()->set('blueprint.stub_path', null);
});

function publishStub(string $name, string $contents): string
{
    $path = base_path('stubs/blueprint/'.$name);
    (new Filesystem)->ensureDirectoryExists(dirname($path));
    file_put_contents($path, $contents);

    return $path;
}

it('uses package stub by default', function () {
    expect($this->renderer->path('module-provider.stub'))
        ->toBe(realpath(__DIR__.'/../../stubs/module-provider.stub'));
});

it('prefers published stubs', function () {
    $published = publishStub('module-provider.stub', 'x');

    expect($this->renderer->path('module-provider.stub'))->toBe($published);
});

it('prefers stub_path over published', function () {
    publishStub('module-provider.stub', 'x');
    file_put_contents($this->tmp.'/module-provider.stub', 'y');
    config()->set('blueprint.stub_path', $this->tmp.'/');

    expect($this->renderer->path('module-provider.stub'))->toBe($this->tmp.'/module-provider.stub');
});

it('skips stub_path when file missing there', function () {
    config()->set('blueprint.stub_path', $this->tmp);

    expect($this->renderer->path('module-provider.stub'))
        ->toBe(realpath(__DIR__.'/../../stubs/module-provider.stub'));
});

it('renders placeholders', function () {
    file_put_contents($this->tmp.'/t.stub', 'A {{ name }} B {{ name }}');
    config()->set('blueprint.stub_path', $this->tmp);

    expect($this->renderer->render('t.stub', ['name' => 'X']))->toBe('A X B X');
});

it('rejects leftover placeholders', function () {
    file_put_contents($this->tmp.'/t.stub', '{{ name }} {{ other }}');
    config()->set('blueprint.stub_path', $this->tmp);

    try {
        $this->renderer->render('t.stub', ['name' => 'X']);
        $this->fail('Expected StubException');
    } catch (StubException $e) {
        expect($e->path)->toBe($this->tmp.'/t.stub')
            ->and($e->reason)->toBe('unknown placeholder {{ other }}')
            ->and($e->getMessage())->toBe("Stub {$this->tmp}/t.stub has unknown placeholder {{ other }}.");
    }
});

it('throws for missing stub', function () {
    expect(fn () => $this->renderer->path('nope.stub'))->toThrow(StubException::class, 'Stub nope.stub not found.');
});
