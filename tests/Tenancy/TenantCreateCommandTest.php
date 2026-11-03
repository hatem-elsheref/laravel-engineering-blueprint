<?php

use App\Models\Tenant;
use Best\Blueprint\Tests\Fixtures\RecordingTenantSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;

const RECORDS_MIGRATION = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant')->nullable();
        });
    }
};
PHP;

beforeEach(function () {
    File::ensureDirectoryExists(database_path('migrations/tenant'));
    File::put(database_path('migrations/tenant/2026_01_01_000000_create_records_table.php'), RECORDS_MIGRATION);
});

afterEach(function () {
    File::deleteDirectory(database_path('migrations/tenant'));
});

function tenantHasTable(string $id, string $table): bool
{
    return tenancy()->find($id)->run(fn () => DB::getSchemaBuilder()->hasTable($table));
}

it('creates a tenant', function () {
    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('✓ Validate name')
        ->expectsOutputToContain('✓ Validate domain')
        ->expectsOutputToContain('✓ Check duplicates')
        ->expectsOutputToContain('✓ Create tenant record')
        ->expectsOutputToContain('✓ Create domain record')
        ->expectsOutputToContain('✓ Create database')
        ->expectsOutputToContain('✓ Run tenant migrations')
        ->expectsOutputToContain('– Seed tenant data (skipped: no Database\Seeders\TenantSeeder)')
        ->expectsOutputToContain('– Create default features (skipped: run engineering:install to set up features)')
        ->expectsOutputToContain('– Create default settings (skipped: run engineering:install to set up settings)')
        ->expectsOutputToContain('Tenant "acme" is ready at http://acme.localhost')
        ->assertExitCode(0);

    $tenant = Tenant::find('acme');

    expect($tenant->tenancy_db_name)->toBe('tenant_acme')
        ->and($tenant->domains()->pluck('domain')->all())->toBe(['acme.localhost'])
        ->and(file_exists(database_path('tenant_acme')))->toBeTrue()
        ->and(tenantHasTable('acme', 'records'))->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasTable('records'))->toBeFalse();
});

it('refuses invalid input', function (array $arguments, string $message) {
    $this->artisan('tenant:create', $arguments)
        ->expectsOutputToContain($message)
        ->assertExitCode(1);

    expect(Tenant::count())->toBe(0);
})->with([
    [['name' => 'Acme'], 'Invalid tenant name "Acme".'],
    [['name' => 'acme', '--domain' => 'acme.other.com'], 'Invalid domain "acme.other.com": it must be a subdomain of localhost.'],
    [['name' => 'acme', '--domain' => 'localhost'], 'Invalid domain "localhost"'],
    [['name' => 'acme', '--database' => 'bad-name'], 'Invalid database name "bad-name".'],
]);

it('requires the tenant-saas profile', function () {
    config()->set('blueprint.profile', 'saas');

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('tenant:create needs the tenant-saas profile with stancl/tenancy set up.')
        ->assertExitCode(1);
});

it('refuses to run inside a tenant', function () {
    $this->artisan('tenant:create', ['name' => 'beta'])->assertExitCode(0);
    tenancy()->initialize(Tenant::find('beta'));

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('Run tenant:create from the central app, not inside a tenant.')
        ->assertExitCode(1);
});

it('refuses a taken domain', function () {
    $this->artisan('tenant:create', ['name' => 'beta', '--domain' => 'acme.localhost'])->assertExitCode(0);

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('Domain "acme.localhost" already belongs to tenant "beta".')
        ->assertExitCode(1);
});

it('refuses a different existing tenant', function () {
    $this->artisan('tenant:create', ['name' => 'acme', '--database' => 'other_db'])->assertExitCode(0);

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('Tenant "acme" already exists with database "other_db" and domain "acme.localhost".')
        ->assertExitCode(1);

    File::delete(database_path('other_db'));
});

it('retries after failure', function () {
    File::put(database_path('migrations/tenant/2025_12_31_000000_broken.php'), "<?php\n\nreturn new class extends Illuminate\\Database\\Migrations\\Migration {\n    public function up(): void { throw new RuntimeException('boom'); }\n};\n");

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('✓ Create tenant record')
        ->expectsOutputToContain('✓ Create database')
        ->expectsOutputToContain('✗ Run tenant migrations: boom')
        ->expectsOutputToContain('Fix the problem and run the same command again; finished steps are skipped.')
        ->assertExitCode(1);

    expect(tenancy()->initialized)->toBeFalse();
    File::delete(database_path('migrations/tenant/2025_12_31_000000_broken.php'));

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('– Create tenant record (already done)')
        ->expectsOutputToContain('– Create domain record (already done)')
        ->expectsOutputToContain('– Create database (already done)')
        ->expectsOutputToContain('✓ Run tenant migrations')
        ->assertExitCode(0);

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('– Run tenant migrations (already done)')
        ->doesntExpectOutputToContain('✓ Create')
        ->assertExitCode(0);
});

it('does not run the app tenant pipeline', function () {
    Event::fake([TenantCreated::class]);

    $this->artisan('tenant:create', ['name' => 'acme'])->assertExitCode(0);

    Event::assertNotDispatched(TenantCreated::class);
});

it('seeds once when the tenant seeder exists', function () {
    require_once __DIR__.'/../Fixtures/RecordingTenantSeeder.php';
    config()->set('blueprint.tenant_seeder', RecordingTenantSeeder::class);

    $this->artisan('tenant:create', ['name' => 'acme'])->expectsOutputToContain('✓ Seed tenant data')->assertExitCode(0);
    $this->artisan('tenant:create', ['name' => 'acme'])->expectsOutputToContain('– Seed tenant data (already done)')->assertExitCode(0);

    expect(tenancy()->find('acme')->run(fn () => DB::table('records')->pluck('tenant')->all()))->toBe(['acme']);
});

it('refuses when tenancy cannot switch databases', function () {
    Event::forget(TenancyInitialized::class);

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('Tenancy is not bootstrapped (App\Providers\TenancyServiceProvider is not registered), so tenant migrations would hit the central database.')
        ->assertExitCode(1);

    expect(Tenant::count())->toBe(0)
        ->and(DB::getSchemaBuilder()->hasTable('records'))->toBeFalse();
});

it('creates default features and settings', function () {
    useTestFlags();
    File::copy(__DIR__.'/../../stubs/features-migration.stub', database_path('migrations/tenant/2026_01_01_000100_create_features_table.php'));
    File::copy(__DIR__.'/../../stubs/settings-migration.stub', database_path('migrations/tenant/2026_01_01_000200_create_settings_table.php'));

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('✓ Create default features')
        ->expectsOutputToContain('– Create default settings (skipped: defaults come from the Settings definitions; no rows needed)')
        ->assertExitCode(0);

    expect(tenancy()->find('acme')->run(fn () => DB::table('features')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all()))
        ->toBe(['orders' => true, 'reports' => false]);

    $this->artisan('tenant:create', ['name' => 'acme'])
        ->expectsOutputToContain('– Create default features (already done)')
        ->assertExitCode(0);
});

it('refuses a database that belongs to another tenant', function () {
    $this->artisan('tenant:create', ['name' => 'acme'])->assertExitCode(0);

    $this->artisan('tenant:create', ['name' => 'evil', '--database' => 'tenant_acme'])
        ->expectsOutputToContain('Database "tenant_acme" already belongs to tenant "acme".')
        ->assertExitCode(1);

    expect(Tenant::find('evil'))->toBeNull();
});

it('refuses to adopt an existing database unless asked', function () {
    touch(database_path('tenant_found'));

    $this->artisan('tenant:create', ['name' => 'found'])
        ->expectsOutputToContain('Database "tenant_found" already exists. Pass --existing-database to use it for this tenant.')
        ->assertExitCode(1);

    $this->artisan('tenant:create', ['name' => 'found', '--existing-database' => true])->assertExitCode(0);
});

it('refuses the central database', function () {
    config()->set('database.connections.testing.database', database_path('central_db'));

    $this->artisan('tenant:create', ['name' => 'acme', '--database' => 'central_db'])
        ->expectsOutputToContain('Database "central_db" is the central database.')
        ->assertExitCode(1);
});
