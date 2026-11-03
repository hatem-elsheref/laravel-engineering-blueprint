<?php

use Best\Blueprint\Tenancy\TenantSetup;
use Best\Blueprint\Tests\Fixtures\RecordTenantJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    File::copyDirectory(__DIR__.'/../Fixtures/tenant-migrations', database_path('migrations/tenant'));

    foreach (['a', 'b'] as $id) {
        app(TenantSetup::class)->run($id, "{$id}.localhost", "tenant_{$id}");
    }
});

afterEach(function () {
    File::deleteDirectory(database_path('migrations/tenant'));
});

function recordsOf(string $id): array
{
    return tenancy()->find($id)->run(fn () => DB::table('records')->pluck('tenant')->all());
}

it('isolates data', function () {
    tenancy()->find('a')->run(fn () => DB::table('records')->insert(['tenant' => 'a']));

    expect(recordsOf('a'))->toBe(['a'])
        ->and(recordsOf('b'))->toBe([])
        ->and(Schema::hasTable('records'))->toBeFalse();
});

it('runs queued jobs in their tenant', function () {
    require_once __DIR__.'/../Fixtures/RecordTenantJob.php';

    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    config()->set('queue.default', 'database');

    tenancy()->initialize(tenancy()->find('a'));
    RecordTenantJob::dispatch();
    tenancy()->end();

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertExitCode(0);

    expect(recordsOf('a'))->toBe(['a'])
        ->and(recordsOf('b'))->toBe([])
        ->and(Schema::hasTable('records'))->toBeFalse();
});

it('runs scheduled tenant work per tenant', function () {
    app(Illuminate\Contracts\Console\Kernel::class)->registerCommand(
        new Illuminate\Foundation\Console\ClosureCommand('test:record-tenant', fn () => DB::table('records')->insert(['tenant' => tenant('id')])),
    );

    $this->artisan('tenants:run', ['commandname' => 'test:record-tenant'])->assertExitCode(0);

    expect(recordsOf('a'))->toBe(['a'])
        ->and(recordsOf('b'))->toBe(['b']);
});

it('pins the database queue to the central connection', function () {
    expect(config('queue.connections.database.connection'))->toBe('testing')
        ->and(config('cache.stores.database.connection'))->toBeNull();
});

it('fails loudly instead of sharing an untaggable cache between tenants', function () {
    config()->set('cache.default', 'file');
    config()->set('tenancy.bootstrappers', [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    ]);

    expect(fn () => tenancy()->find('a')->run(fn () => cache()->put('greeting', 'hello a')))
        ->toThrow(BadMethodCallException::class, 'does not support tagging');
});
