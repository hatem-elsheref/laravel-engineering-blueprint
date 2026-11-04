<?php

use Best\Blueprint\Tests\MysqlIntegration\MysqlTestCase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(MysqlTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('blueprint.profile', 'modular');
    config()->set('blueprint.module_path', 'app/Modules');

    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    $this->schema = sys_get_temp_dir().'/leb-mysql-schema-'.uniqid('', true).'.yaml';
    file_put_contents($this->schema, <<<'YAML'
softDeletes: true
fields:
  - name: code
    type: string
    unique: true
YAML);

    $this->artisan('make:module', ['name' => 'Inventory', '--api' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Inventory',
        'entity' => 'Sku',
        '--schema' => $this->schema,
    ])->assertExitCode(0);

    $this->migration = glob(database_path('migrations/*_create_skus_table.php'))[0] ?? null;
});

afterEach(function (): void {
    if (isset($this->schema) && is_file($this->schema)) {
        @unlink($this->schema);
    }

    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Inventory'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));

    if (isset($this->providersPath, $this->providers)) {
        file_put_contents($this->providersPath, $this->providers);
    }
});

it('migrates a schema dsl table on mysql', function (): void {
    expect($this->migration)->not->toBeNull();

    $this->artisan('migrate')->assertExitCode(0);

    expect(Schema::hasTable('skus'))->toBeTrue()
        ->and(Schema::hasColumn('skus', 'deleted_at'))->toBeTrue();

    DB::table('skus')->insert([
        'code' => 'Wire-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('skus')->insert([
        'code' => 'Wire-1',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
