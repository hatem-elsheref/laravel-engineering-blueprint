<?php

namespace Best\Blueprint\Permissions;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Support\CrudNames;

/**
 * What make:crud --permissions adds for one entity: the permission group, the definitions class it belongs
 * to, and the policy and test fragments. The generated policy checks <group>.<ability> with
 * checkPermissionTo(), which is false (403) for a permission that was never synced. With --owned the group
 * also has <group>.any and OwnedCrud adds the owner parts.
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
        public readonly bool $owned = false,
    ) {}

    public function group(): string
    {
        return $this->names->uri;
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return $this->owned ? [...self::ABILITIES, OwnedCrud::ABILITY] : self::ABILITIES;
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

        if (($unsupported = app(PermissionsDetector::class)->unsupported()) !== null) {
            return $unsupported;
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

        // The tests' user holds the four abilities, not <group>.any: owned tests grant that where they need it.
        $granted = array_map(fn (string $ability) => "'{$permissions->group()}.{$ability}'", self::ABILITIES);

        return [
            'permissionUses' => ', InteractsWithPermissions::class',
            'permissionSetup' => '    $this->grantPermissions($this->user, '.implode(', ', $granted).");\n",
            'permissionTest' => ($web ? $permissions->webTests($formInput) : $permissions->apiTests())
                .($permissions->owned ? (new OwnedCrud($permissions))->tests($web, $formInput) : ''),
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
        $index = $this->testUrl("/api/{$this->names->uri}");
        $one = $this->testUrl("/api/{$this->names->uri}/{{$v}->id}", true);
        $record = $this->recordDenied();

        return "\nit('denies users without the permission', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    {$v} = {$e}::factory()->create();\n\n"
            ."    \$this->actAsApiUser(\$user)->getJson({$index})->assertForbidden();\n"
            ."    \$this->actAsApiUser(\$user)->postJson({$index}, {$e}::factory()->raw())->assertForbidden();\n"
            ."    \$this->actAsApiUser(\$user)->getJson({$one})->{$record}();\n"
            ."    \$this->actAsApiUser(\$user)->putJson({$one}, {$e}::factory()->raw())->{$record}();\n"
            ."    \$this->actAsApiUser(\$user)->deleteJson({$one})->{$record}();\n"
            ."});\n"
            .$this->superAdminTest('getJson', $index, web: false);
    }

    private function webTests(string $formInput): string
    {
        $e = $this->names->entity;
        $v = '$'.$this->names->variable;
        $index = $this->testUrl("/{$this->names->uri}", web: true);
        $create = $this->testUrl("/{$this->names->uri}/create", web: true);
        $one = $this->testUrl("/{$this->names->uri}/{{$v}->id}", true, true);
        $edit = $this->testUrl("/{$this->names->uri}/{{$v}->id}/edit", true, true);
        $record = $this->recordDenied();

        return "\nit('denies users without the permission', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    {$v} = {$e}::factory()->create();\n\n"
            ."    \$this->actingAs(\$user)->get({$index})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->get({$create})->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->post({$index}, {$formInput}())->assertForbidden();\n"
            ."    \$this->actingAs(\$user)->get({$one})->{$record}();\n"
            ."    \$this->actingAs(\$user)->get({$edit})->{$record}();\n"
            ."    \$this->actingAs(\$user)->put({$one}, {$formInput}())->{$record}();\n"
            ."    \$this->actingAs(\$user)->delete({$one})->{$record}();\n"
            ."});\n"
            .$this->superAdminTest('get', $index, web: true);
    }

    /** Owned: a record the user may not view (here another user's) is "not found"; otherwise 403. */
    private function recordDenied(): string
    {
        return $this->owned ? 'assertNotFound' : 'assertForbidden';
    }

    private function superAdminTest(string $method, string $index, bool $web): string
    {
        $act = $web ? '$this->actingAs' : '$this->actAsApiUser';

        return "\nit('lets a super-admin in', function (): void {\n"
            ."    \$user = User::factory()->create();\n"
            ."    \$this->makeSuperAdmin(\$user);\n\n"
            ."    {$act}(\$user)->{$method}({$index})->assertOk();\n"
            ."});\n";
    }

    /** A URL expression as the test stubs write it. */
    public function testUrl(string $path, bool $interpolated = false, bool $web = false): string
    {
        $literal = $interpolated ? "\"{$path}\"" : "'{$path}'";

        return match (true) {
            $this->names->tenant => "\$this->tenantUrl({$literal})",
            $web => "url({$literal})",
            default => $literal,
        };
    }
}
