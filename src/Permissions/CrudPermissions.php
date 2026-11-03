<?php

namespace Best\Blueprint\Permissions;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Support\CrudNames;

/**
 * What make:crud --permissions adds for one entity: the permission group, the definitions class it belongs
 * to, and the policy and test fragments. The generated policy checks <group>.<ability> with
 * checkPermissionTo(), which is false (403) for a permission that was never synced.
 */
final class CrudPermissions
{
    /** @var list<string> */
    public const ABILITIES = ['view', 'create', 'update', 'delete'];

    /** Marks a policy generated in permissions mode (used to match the mode when adding a side). */
    public const POLICY_MARKER = 'checkPermissionTo(';

    public function __construct(
        public readonly CrudNames $names,
        public readonly bool $central,
    ) {}

    public function group(): string
    {
        return $this->names->uri;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return self::ABILITIES;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_map(fn (string $ability) => "{$this->group()}.{$ability}", $this->abilities());
    }

    /** CentralPermissions for a central module (tenant-saas), Permissions otherwise. */
    public function definitionsClass(): string
    {
        return $this->central ? Definitions::centralPermissions() : Definitions::permissions();
    }

    public function syncCommand(): string
    {
        return 'php artisan '.$this->policyValues()['sync'];
    }

    /**
     * Why --permissions can't be used yet, or null when permissions are set up.
     */
    public static function setupError(string $definitionsClass): ?string
    {
        if (! app(PermissionsDetector::class)->spatie()) {
            return '--permissions needs spatie/laravel-permission. Run: composer require spatie/laravel-permission && php artisan engineering:install --permissions';
        }

        if (! class_exists($definitionsClass)) {
            return "Permission definitions not found ({$definitionsClass}). Run: php artisan engineering:install --permissions";
        }

        return null;
    }

    public static function usedBy(string $policySource): bool
    {
        return str_contains($policySource, self::POLICY_MARKER);
    }

    /**
     * Values for policy-permissions.stub.
     *
     * @return array<string, string>
     */
    public function policyValues(): array
    {
        return [
            'definitions' => class_basename($this->definitionsClass()),
            'sync' => 'permissions:sync'.($this->central ? ' --central' : ''),
        ];
    }

    /**
     * Values for the {{ permissionUses }}, {{ permissionSetup }} and {{ permissionTest }} test stub placeholders;
     * empty strings without --permissions.
     *
     * @return array{permissionUses: string, permissionSetup: string, permissionTest: string}
     */
    public static function testValues(?self $permissions, bool $web, string $formInput = ''): array
    {
        if ($permissions === null) {
            return ['permissionUses' => '', 'permissionSetup' => '', 'permissionTest' => ''];
        }

        return [
            'permissionUses' => ', InteractsWithPermissions::class',
            'permissionSetup' => '    $this->grantPermissions($this->user, '.implode(', ', array_map(fn (string $p) => "'{$p}'", $permissions->permissions())).");\n",
            'permissionTest' => $web ? $permissions->webTests($formInput) : $permissions->apiTests(),
        ];
    }

    /** Whether rendered tests set up the permissions they need (an old published stub may drop them). */
    public static function testsSetUp(string $test): bool
    {
        return str_contains($test, 'grantPermissions(') && str_contains($test, 'InteractsWithPermissions::class');
    }

    private function apiTests(): string
    {
        $e = $this->names->entity;
        $v = '$'.$this->names->variable;
        $index = $this->url("/api/{$this->names->uri}");
        $one = $this->url("/api/{$this->names->uri}/{{$v}->id}", true);

        return "\nit('denies users without the permission', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    {$v} = {$e}::factory()->create();\n\n"
            ."    \$this->actingAs(\$user)->getJson({$index})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->postJson({$index}, {$e}::factory()->raw())->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->getJson({$one})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->putJson({$one}, {$e}::factory()->raw())->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->deleteJson({$one})->assertForbidden();\n"
            ."});\n"
            .$this->superAdminTest('getJson', $index);
    }

    private function webTests(string $formInput): string
    {
        $e = $this->names->entity;
        $v = '$'.$this->names->variable;
        $index = $this->url("/{$this->names->uri}", web: true);
        $create = $this->url("/{$this->names->uri}/create", web: true);
        $one = $this->url("/{$this->names->uri}/{{$v}->id}", true, true);
        $edit = $this->url("/{$this->names->uri}/{{$v}->id}/edit", true, true);

        return "\nit('denies users without the permission', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    {$v} = {$e}::factory()->create();\n\n"
            ."    \$this->actingAs(\$user)->get({$index})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->get({$create})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->post({$index}, {$formInput}())->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->get({$one})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->get({$edit})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->put({$one}, {$formInput}())->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->delete({$one})->assertForbidden();\n"
            ."});\n"
            .$this->superAdminTest('get', $index);
    }

    private function superAdminTest(string $method, string $index): string
    {
        return "\nit('lets a super-admin in', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    \$this->makeSuperAdmin(\$user);\n\n"
            ."    \$this->actingAs(\$user)->{$method}({$index})->assertOk();\n"
            ."});\n";
    }

    /** A URL expression as the test stubs write it. */
    private function url(string $path, bool $interpolated = false, bool $web = false): string
    {
        $literal = $interpolated ? "\"{$path}\"" : "'{$path}'";

        return match (true) {
            $this->names->tenant => "\$this->tenantUrl({$literal})",
            $web => "url({$literal})",
            default => $literal,
        };
    }
}
