<?php

namespace Best\Blueprint\Permissions;

use Best\Blueprint\Support\CrudField;
use Best\Blueprint\Support\CrudNames;
use Best\Blueprint\Support\StubRenderer;

/**
 * What make:crud --owned adds on top of --permissions: records belong to the user who creates them
 * (user_id, set from the signed-in user, never from input). The policy allows <group>.<ability> on the
 * user's own records, or on every record with <group>.any too; others' records answer "not found".
 */
final class OwnedCrud
{
    /** Marks a policy generated in owned mode (used to match the mode when adding a side). */
    public const POLICY_MARKER = 'denyAsNotFound(';

    public const ABILITY = 'any';

    private const USER = 'App\\Models\\User';

    public function __construct(private readonly CrudPermissions $permissions) {}

    /**
     * @param  list<CrudField>  $fields
     */
    public static function fieldError(array $fields): ?string
    {
        foreach ($fields as $field) {
            if (in_array($field->column, ['user_id', 'user'], true)) {
                return '--owned adds the user_id column and the user relation; drop the user_id and user fields.';
            }
        }

        return null;
    }

    public static function usedBy(string $policySource): bool
    {
        return str_contains($policySource, self::POLICY_MARKER);
    }

    /**
     * Placeholder values for controller.stub and web-controller.stub, and the imports they need.
     *
     * @return array{values: array{indexParameters: string, listArguments: string, createArguments: string}, imports: list<string>}
     */
    public static function controllerValues(bool $owned, string $plural): array
    {
        return $owned
            ? ['values' => ['indexParameters' => "Request \$request, List{$plural} \$query", 'listArguments' => '$request->user()', 'createArguments' => ', $request->user()'], 'imports' => ['Illuminate\\Http\\Request']]
            : ['values' => ['indexParameters' => "List{$plural} \$query", 'listArguments' => '', 'createArguments' => ''], 'imports' => []];
    }

    /** {{ factoryOwner }} in the test stubs: the tests' own records belong to $this->user. */
    public static function factoryOwner(bool $owned): string
    {
        return $owned ? '->for($this->user)' : '';
    }

    public function migrationColumn(): string
    {
        return "            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n";
    }

    public function factoryLine(): string
    {
        return "            'user_id' => User::factory(),\n";
    }

    /** {{ relations }} in model.stub. */
    public function modelRelation(): string
    {
        return "\n    /**\n     * @return BelongsTo<User, \$this>\n     */\n    public function user(): BelongsTo\n    {\n        return \$this->belongsTo(User::class);\n    }\n";
    }

    /** @return list<string> */
    public static function modelImports(): array
    {
        return [self::USER, 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo'];
    }

    public static function userClass(): string
    {
        return self::USER;
    }

    /**
     * Why the rendered files would drop owner scoping (e.g. an old published stub), or null.
     *
     * @param  array<string, string>  $generated  base-relative path => contents
     */
    public static function stubError(StubRenderer $stubs, array $generated, CrudNames $names, bool $api, bool $web): ?string
    {
        $paths = $names->files();
        $checks = [
            'model' => ['model.stub', 'function user(): BelongsTo', 'a valid owned model (no user() relation)'],
            'query' => ['query-owned.stub', 'whereBelongsTo(', 'a valid owned query (no whereBelongsTo)'],
            'policy' => ['policy-owned.stub', self::POLICY_MARKER, 'a valid owned policy (no denyAsNotFound)'],
            'create' => ['crud-create-action-owned.stub', '->user()->associate($owner)', 'a valid owned create action (no ->user()->associate($owner))'],
            'update' => ['request-update-owned.stub', 'Gate::inspect(', 'a valid owned update request (no Gate::inspect)'],
        ];

        foreach ($checks as $key => [$stub, $needle, $what]) {
            if (isset($generated[$paths[$key]]) && ! str_contains($generated[$paths[$key]], $needle)) {
                return "Stub {$stubs->path($stub)} did not produce {$what}. Update or delete it, then re-run.";
            }
        }

        $controllers = array_filter(['controller' => $api ? 'controller.stub' : null, 'pageController' => $web ? 'web-controller.stub' : null]);

        foreach ($controllers as $key => $stub) {
            $code = $generated[$paths[$key]];

            if (! str_contains($code, '->handle($request->user())') || ! str_contains($code, ', $request->user())')) {
                return "Stub {$stubs->path($stub)} did not produce a valid owned controller (no \$request->user() for the list and the create action). Update or delete it, then re-run.";
            }
        }

        $tests = array_filter([
            'test' => $api ? ($names->tenant ? 'test-tenant.stub' : 'test.stub') : null,
            'webTest' => $web ? ($names->tenant ? 'test-web-tenant.stub' : 'test-web.stub') : null,
        ]);

        foreach ($tests as $key => $stub) {
            if (isset($generated[$paths[$key]]) && ! str_contains($generated[$paths[$key]], self::factoryOwner(true))) {
                return "Stub {$stubs->path($stub)} did not produce valid tests (no owner for the user's records). Update or delete it, then re-run.";
            }
        }

        return null;
    }

    /** The owned-mode tests appended to {{ permissionTest }}. */
    public function tests(bool $web, string $formInput = ''): string
    {
        $names = $this->permissions->names;
        $e = $names->entity;
        $v = '$'.$names->variable;
        $p = $this->permissions;
        $any = '    $this->grantPermissions($this->user, '.implode(', ', array_map(fn (string $name) => "'{$name}'", $p->permissions())).");\n";

        $index = $web ? $p->testUrl("/{$names->uri}", web: true) : $p->testUrl("/api/{$names->uri}");
        $one = $web ? $p->testUrl("/{$names->uri}/{{$v}->id}", true, true) : $p->testUrl("/api/{$names->uri}/{{$v}->id}", true);
        $edit = $p->testUrl("/{$names->uri}/{{$v}->id}/edit", true, true);
        $see = "\"/{$names->uri}/{{$v}->id}\"";

        if ($web) {
            $hidden = "    \$this->actingAs(\$this->user)->get({$one})->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->get({$edit})->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->put({$one}, {$formInput}())->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->delete({$one})->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->get({$index})->assertOk()->assertDontSee({$see});\n";
            $anyHolder = "    \$this->actingAs(\$this->user)->get({$one})->assertOk();\n"
                ."    \$this->actingAs(\$this->user)->get({$index})->assertOk()->assertSee({$see});\n"
                ."    \$this->actingAs(\$this->user)->delete({$one})->assertRedirectContains('/{$names->uri}');\n";
            $update = "    \$this->actingAs(\$this->user)->put({$one}, {$formInput}(['user_id' => \$this->user->id]))->assertSessionHasNoErrors();\n";
            $superAdmin = "    \$this->actingAs(\$user)->get({$index})->assertOk()->assertSee({$see});\n";
        } else {
            $hidden = "    \$this->actingAs(\$this->user)->getJson({$one})->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->putJson({$one}, {$e}::factory()->raw())->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->deleteJson({$one})->assertNotFound();\n"
                ."    \$this->actingAs(\$this->user)->getJson({$index})->assertOk()->assertJsonCount(0, 'data');\n";
            $anyHolder = "    \$this->actingAs(\$this->user)->getJson({$one})->assertOk();\n"
                ."    \$this->actingAs(\$this->user)->getJson({$index})->assertOk()->assertJsonCount(1, 'data');\n"
                ."    \$this->actingAs(\$this->user)->deleteJson({$one})->assertNoContent();\n";
            $update = "    \$this->actingAs(\$this->user)->putJson({$one}, {$e}::factory()->raw(['user_id' => \$this->user->id]))->assertOk();\n";
            $superAdmin = "    \$this->actingAs(\$user)->getJson({$index})->assertOk()->assertJsonCount(2, 'data');\n";
        }

        return "\nit(\"hides other users' {$names->uri}\", function (): void {\n"
            ."    {$v} = {$e}::factory()->create();\n\n"
            .$hidden."\n"
            ."    expect({$e}::count())->toBe(1);\n"
            ."});\n"
            ."\nit('lets an any-holder act on every {$names->variable}', function (): void {\n"
            .$any
            ."    {$v} = {$e}::factory()->create();\n\n"
            .$anyHolder
            ."});\n"
            ."\nit('keeps the owner when an any-holder updates', function (): void {\n"
            .$any
            ."    {$v} = {$e}::factory()->create();\n\n"
            .$update."\n"
            ."    expect({$v}->fresh()->user_id)->toEqual({$v}->user_id);\n"
            ."});\n"
            ."\nit('lets a super-admin see every {$names->variable}', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    \$this->makeSuperAdmin(\$user);\n"
            ."    {$v} = {$e}::factory()->create();\n"
            ."    {$e}::factory()->for(\$user)->create();\n\n"
            .$superAdmin
            ."});\n";
    }
}
