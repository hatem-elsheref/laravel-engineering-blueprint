<?php

use Best\Blueprint\Permissions\DefinitionsFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.module_path', 'app/Modules');
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);
    makePermissionDefinitions();
    $this->definitions = app_path('Shared/Permissions/Permissions.php');
    $this->artisan('make:module', ['name' => 'Orders', '--api' => true, '--web' => true])->assertExitCode(0);
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::deleteDirectory(app_path('Shared'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Orders'));
    File::deleteDirectory(resource_path('views/orders'));
    File::deleteDirectory(resource_path('views/layouts'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(base_path('stubs'));
    file_put_contents($this->providersPath, $this->providers);
});

function ownedLints(string $path): void
{
    $lint = new Process([PHP_BINARY, '-l', $path]);
    $lint->run();
    expect($lint->getExitCode())->toBe(0, $path.': '.$lint->getOutput());
}

function makeOwnedOrder(array $options = []): void
{
    test()->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => 'name:string', '--permissions' => true, '--owned' => true] + $options)
        ->assertExitCode(0);
}

it('refuses --owned without --permissions', function () {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--owned' => true])
        ->expectsOutputToContain('--owned needs --permissions.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});

it('refuses a field that would clash with the owner', function (string $field) {
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => "{$field}:int", '--permissions' => true, '--owned' => true])
        ->expectsOutputToContain('--owned adds the user_id column and the user relation; drop the user_id and user fields.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
})->with(['user_id', 'userId', 'user']);

it('adds the group with any', function () {
    makeOwnedOrder();

    expect(file_get_contents($this->definitions))->toContain("'orders' => ['view', 'create', 'update', 'delete', 'any'],");
});

it('adds the owner column, relation and factory line', function () {
    makeOwnedOrder();

    $migration = glob(database_path('migrations/*_create_orders_table.php'))[0];
    $model = file_get_contents(app_path('Modules/Orders/Models/Order.php'));
    $factory = file_get_contents(database_path('factories/Orders/OrderFactory.php'));

    expect(file_get_contents($migration))->toContain("            \$table->id();\n            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n            \$table->string('name');\n")
        ->and($model)->toContain('use App\Models\User;')
        ->toContain('use Illuminate\Database\Eloquent\Relations\BelongsTo;')
        ->toContain("     * @return BelongsTo<User, \$this>\n     */\n    public function user(): BelongsTo\n    {\n        return \$this->belongsTo(User::class);\n    }")
        ->toContain("protected \$fillable = [\n        'name',\n    ];")
        ->not->toContain("'user_id',")
        ->and($factory)->toContain('use App\Models\User;')
        ->toContain("            'user_id' => User::factory(),\n            'name' => ");

    foreach ([$migration, app_path('Modules/Orders/Models/Order.php'), database_path('factories/Orders/OrderFactory.php')] as $path) {
        ownedLints($path);
    }
});

it('sets the owner in the create action, never from input', function () {
    makeOwnedOrder();

    $action = file_get_contents(app_path('Modules/Orders/Actions/CreateOrder.php'));
    $data = file_get_contents(app_path('Modules/Orders/Data/OrderData.php'));

    expect($action)->toContain('public function handle(OrderData $data, User $owner): Order')
        ->toContain('new Order($data->toArray())')
        ->toContain('->user()->associate($owner);')
        ->toContain('->save();')
        ->toContain('DB::transaction(')
        ->toContain('use App\Models\User;')
        ->and($data)->not->toContain('user');

    ownedLints(app_path('Modules/Orders/Actions/CreateOrder.php'));
});

it('scopes the list to the user unless they may see every record', function () {
    makeOwnedOrder();

    $query = file_get_contents(app_path('Modules/Orders/Queries/ListOrders.php'));

    expect($query)->toContain('public function handle(User $user, ?int $perPage = null): LengthAwarePaginator')
        ->toContain('ListPerPage::resolve($perPage)')
        ->toContain("->when(! \$user->can('any', Order::class), fn (\$query) => \$query->whereBelongsTo(\$user))")
        ->toContain('use App\Models\User;');

    ownedLints(app_path('Modules/Orders/Queries/ListOrders.php'));
});

it('generates a policy that checks the permission and the owner', function () {
    makeOwnedOrder();

    $path = app_path('Modules/Orders/Policies/OrderPolicy.php');
    $policy = file_get_contents($path);

    expect($policy)->toContain("private const GROUP = 'orders';")
        ->toContain("public function viewAny(User \$user): bool\n    {\n        return \$user->checkPermissionTo(self::GROUP.'.view');")
        ->toContain("public function create(User \$user): bool\n    {\n        return \$user->checkPermissionTo(self::GROUP.'.create');")
        ->toContain('public function view(User $user, Order $order): Response')
        ->toContain('public function update(User $user, Order $order): Response')
        ->toContain('public function delete(User $user, Order $order): Response')
        ->toContain("public function any(User \$user): bool\n    {\n        return \$user->checkPermissionTo(self::GROUP.'.any');")
        ->toContain('Response::deny()')
        ->toContain('Response::denyAsNotFound()')
        ->toContain("if (! \$user->checkPermissionTo(self::GROUP.'.view') || ! (\$order->user()->is(\$user) || \$this->any(\$user))) {\n            return Response::denyAsNotFound();\n        }\n\n        return \$user->checkPermissionTo(self::GROUP.'.'.\$ability) ? Response::allow() : Response::deny();")
        ->toContain('Response::allow()')
        ->toContain('use Illuminate\Auth\Access\Response;');

    ownedLints($path);
});

it('keeps the not-found answer for updates by non-owners', function () {
    makeOwnedOrder();

    $request = file_get_contents(app_path('Modules/Orders/Requests/UpdateOrderRequest.php'));

    expect($request)->toContain('public function authorize(): Response')
        ->toContain("return Gate::inspect('update', \$this->route('order'));")
        ->and(file_get_contents(app_path('Modules/Orders/Requests/StoreOrderRequest.php')))->toContain("can('create', Order::class)");

    ownedLints(app_path('Modules/Orders/Requests/UpdateOrderRequest.php'));
});

it('passes the signed-in user from the controllers', function () {
    makeOwnedOrder(['--api' => true, '--web' => true]);

    foreach (['OrderController', 'OrderPageController'] as $class) {
        $path = app_path("Modules/Orders/Controllers/{$class}.php");
        $controller = file_get_contents($path);

        expect($controller)->toContain('public function index(Request $request, ListOrders $query)')
            ->toContain('$query->handle($request->user(), ListPerPage::fromRequest($request))')
            ->toContain('$action->handle(OrderData::fromRequest($request), $request->user())')
            ->toContain('use Illuminate\Http\Request;');

        ownedLints($path);
    }
});

it('writes the owned tests', function () {
    makeOwnedOrder(['--api' => true, '--web' => true]);

    foreach (['OrderApiTest', 'OrderWebTest'] as $name) {
        $path = base_path("tests/Feature/Modules/Orders/{$name}.php");
        $test = file_get_contents($path);

        expect($test)->toContain("    \$this->grantPermissions(\$this->user, 'orders.view', 'orders.create', 'orders.update', 'orders.delete');\n})")
            ->toContain('Order::factory()->for($this->user)->create();')
            ->toContain($name === 'OrderApiTest'
                ? "    \$this->actAsApiUser(\$user)->getJson('/api/orders')->assertForbidden();\n    \$this->actAsApiUser(\$user)->postJson('/api/orders', Order::factory()->raw())->assertForbidden();\n    \$this->actAsApiUser(\$user)->getJson(\"/api/orders/{\$order->id}\")->assertNotFound();\n    \$this->actAsApiUser(\$user)->putJson(\"/api/orders/{\$order->id}\", Order::factory()->raw())->assertNotFound();\n    \$this->actAsApiUser(\$user)->deleteJson(\"/api/orders/{\$order->id}\")->assertNotFound();"
                : '    $this->actingAs($user)->get(url("/orders/{$order->id}/edit"))->assertNotFound();')
            ->toContain("it(\"hides other users' orders\"")
            ->toContain("it('lets an any-holder act on every order'")
            ->toContain("\$this->grantPermissions(\$this->user, 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.any');")
            ->toContain("it('keeps the owner when an any-holder updates'")
            ->toContain("it('lets a super-admin see every order'")
            ->toContain("it('denies an owner without the ability'")
            ->toContain("    \$this->grantPermissions(\$this->user, 'orders.view');\n    \$order = Order::factory()->for(\$this->user, 'user')->create();")
            ->toContain('->assertNotFound()');

        ownedLints($path);
    }
});

it('keeps the owned mode when adding a side', function () {
    makeOwnedOrder();

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true, '--permissions' => true])
        ->expectsOutputToContain('The existing Order CRUD is owned; add --owned.')
        ->assertExitCode(1);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Coupon', '--permissions' => true])->assertExitCode(0);
    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Coupon', '--web' => true, '--permissions' => true, '--owned' => true])
        ->expectsOutputToContain('The existing Coupon CRUD is not owned; leave --owned out.')
        ->assertExitCode(1);

    expect(file_exists(app_path('Modules/Orders/Controllers/OrderPageController.php')))->toBeFalse()
        ->and(file_exists(app_path('Modules/Orders/Controllers/CouponPageController.php')))->toBeFalse();

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--web' => true, '--permissions' => true, '--owned' => true])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/Controllers/OrderPageController.php')))->toContain('$query->handle($request->user(), ListPerPage::fromRequest($request))');
});

it('refuses an old controller stub that drops owner scoping', function (string $stub, array $sides) {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    $old = str_replace(
        ['{{ indexParameters }}', '{{ listArguments }}', '{{ createArguments }}'],
        ['List{{ plural }} $query', '', ''],
        file_get_contents(__DIR__."/../../stubs/{$stub}"),
    );
    file_put_contents(base_path("stubs/blueprint/{$stub}"), $old);
    $definitions = file_get_contents($this->definitions);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true, '--owned' => true] + $sides)
        ->expectsOutputToContain("{$stub} did not produce a valid owned controller (no \$request->user() for the list and the create action). Update or delete it, then re-run.")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse()
        ->and(file_get_contents($this->definitions))->toBe($definitions);
})->with([
    'api' => ['controller.stub', ['--api' => true]],
    'web' => ['web-controller.stub', ['--web' => true]],
]);

it('refuses published owned stubs that drop the owner checks', function (string $stub, string $from, string $message) {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path("stubs/blueprint/{$stub}"), file_get_contents(__DIR__."/../../stubs/{$from}"));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true, '--owned' => true])
        ->expectsOutputToContain("{$stub} did not produce {$message}. Update or delete it, then re-run.")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
})->with([
    'query' => ['query-owned.stub', 'query.stub', 'a valid owned query (no whereBelongsTo)'],
    'policy' => ['policy-owned.stub', 'policy-permissions.stub', 'a valid owned policy (no denyAsNotFound)'],
    'create action' => ['crud-create-action-owned.stub', 'crud-create-action.stub', 'a valid owned create action (no ->user()->associate($owner))'],
    'update request' => ['request-update-owned.stub', 'request.stub', 'a valid owned update request (no Gate::inspect)'],
]);

it('refuses an old test stub that drops the owner in the factory', function (string $stub, array $sides) {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path("stubs/blueprint/{$stub}"), str_replace('{{ factoryOwner }}', '', file_get_contents(__DIR__."/../../stubs/{$stub}")));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true, '--owned' => true] + $sides)
        ->expectsOutputToContain("{$stub} did not produce valid tests (no owner for the user's records). Update or delete it, then re-run.")
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
})->with([
    'api' => ['test.stub', ['--api' => true]],
    'web' => ['test-web.stub', ['--web' => true]],
]);

it('refuses an old model stub that drops the owner relation', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));
    file_put_contents(base_path('stubs/blueprint/model.stub'), str_replace('{{ relations }}', '', file_get_contents(__DIR__.'/../../stubs/model.stub')));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--permissions' => true, '--owned' => true])
        ->expectsOutputToContain('model.stub did not produce a valid owned model (no user() relation). Update or delete it, then re-run.')
        ->assertExitCode(1);

    expect(is_dir(app_path('Modules/Orders/Models')))->toBeFalse();
});

it('keeps working with old published controller and test stubs without --owned', function () {
    File::ensureDirectoryExists(base_path('stubs/blueprint'));

    foreach (['controller.stub', 'web-controller.stub', 'test.stub', 'test-web.stub', 'model.stub'] as $stub) {
        $old = str_replace(
            ['{{ indexParameters }}', '{{ listArguments }}', '{{ createArguments }}', '{{ factoryOwner }}', '{{ relations }}'],
            ['List{{ plural }} $query', '', '', '', ''],
            file_get_contents(__DIR__."/../../stubs/{$stub}"),
        );
        file_put_contents(base_path("stubs/blueprint/{$stub}"), $old);
    }

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--api' => true, '--web' => true, '--permissions' => true])->assertExitCode(0);

    expect(file_get_contents(app_path('Modules/Orders/Controllers/OrderController.php')))->toContain('public function index(ListOrders $query)');
});

it('warns when the group already exists without the abilities this CRUD needs', function () {
    // e.g. another module's CRUD made the 'orders' group earlier, without --owned.
    file_put_contents($this->definitions, DefinitionsFile::addGroup(
        file_get_contents($this->definitions), 'orders', ['view', 'create', 'update', 'delete'],
    ));

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => 'name:string', '--permissions' => true, '--owned' => true])
        ->expectsOutputToContain("The 'orders' group in app/Shared/Permissions/Permissions.php has no 'any'; another CRUD may use it. Add 'any' to 'orders' in app/Shared/Permissions/Permissions.php")
        ->assertExitCode(0);
});

it('does not warn when the group has every ability', function () {
    makeOwnedOrder(['--web' => true]);

    $this->artisan('make:crud', ['module' => 'Orders', 'entity' => 'Order', '--fields' => 'name:string', '--permissions' => true, '--owned' => true, '--api' => true])
        ->doesntExpectOutputToContain('group in app/Shared/Permissions/Permissions.php has no')
        ->assertExitCode(0);
});
