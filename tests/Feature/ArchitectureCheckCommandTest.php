<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->root = base_path('app/Modules');
    File::deleteDirectory($this->root);
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('registers engineering:architecture', function (): void {
    expect(collect(Artisan::all())->keys())->toContain('engineering:architecture');
});

it('passes when modules are clean', function (): void {
    File::ensureDirectoryExists($this->root.'/Shop/Actions');
    file_put_contents($this->root.'/Shop/Actions/List.php', <<<'PHP'
<?php
namespace App\Modules\Shop\Actions;
final class List {}
PHP);

    $this->artisan('engineering:architecture')->expectsOutputToContain('No module boundary violations')->assertExitCode(0);
});

it('fails when a cross-module controller is imported', function (): void {
    File::ensureDirectoryExists($this->root.'/Shop/Actions');
    file_put_contents($this->root.'/Shop/Actions/Bad.php', <<<'PHP'
<?php
namespace App\Modules\Shop\Actions;
use App\Modules\Billing\Controllers\InvoiceController;
final class Bad {}
PHP);

    $this->artisan('engineering:architecture')
        ->expectsOutputToContain('[Shop] must not import')
        ->assertExitCode(1);
});
