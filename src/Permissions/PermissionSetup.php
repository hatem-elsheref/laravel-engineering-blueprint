<?php

namespace Best\Blueprint\Permissions;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Generates the permission definition classes, spatie's migration and the User trait (idempotent).
 * Callers must check PermissionsDetector::spatie() first.
 */
class PermissionSetup
{
    private const USER = 'App\Models\User';

    public function __construct(
        private readonly Filesystem $files,
        private readonly StubRenderer $stubs,
    ) {}

    /**
     * @return list<string>
     */
    public function run(Profile $profile): array
    {
        $shared = trim((string) config('blueprint.shared_path', 'app/Shared'), '/');
        $namespace = Definitions::sharedNamespace();

        if ($namespace === null) {
            return [sprintf('shared_path must be inside app/ (got "%s"). Permissions were not set up.', config('blueprint.shared_path'))];
        }

        $tenancy = $profile->usesTenancy();
        $lines = [
            $this->definition("{$shared}/Permissions/Permissions.php", "{$namespace}\\Permissions", 'Permissions', $tenancy ? 'tenant' : 'app'),
        ];

        if ($tenancy) {
            $lines[] = $this->definition("{$shared}/Permissions/CentralPermissions.php", "{$namespace}\\Permissions", 'CentralPermissions', 'central');
        }

        $lines[] = $this->migration('database/migrations');

        if ($tenancy) {
            $lines[] = $this->migration('database/migrations/tenant');
        }

        $lines[] = $this->userModel();

        return $lines;
    }

    private function definition(string $relative, string $namespace, string $class, string $side): string
    {
        if ($this->files->exists(base_path($relative))) {
            return "already set up: {$relative}";
        }

        $this->files->ensureDirectoryExists(dirname(base_path($relative)));
        $this->files->put(base_path($relative), $this->stubs->render('permissions-definitions.stub', [
            'namespace' => $namespace, 'class' => $class, 'side' => $side,
        ]));

        return "created {$relative}";
    }

    private function migration(string $folder): string
    {
        $label = $folder === 'database/migrations/tenant' ? 'tenant permission tables migration' : 'permission tables migration';

        if ((glob(base_path("{$folder}/*_create_permission_tables.php")) ?: []) !== []) {
            return "already set up: {$label}";
        }

        $relative = "{$folder}/".date('Y_m_d_').'000300_create_permission_tables.php';
        $this->files->ensureDirectoryExists(dirname(base_path($relative)));
        $this->files->copy($this->migrationStub(), base_path($relative));

        return "created {$relative}";
    }

    private function migrationStub(): string
    {
        $file = (string) (new ReflectionClass(PermissionServiceProvider::class))->getFileName();

        return dirname($file, 2).'/database/migrations/create_permission_tables.php.stub';
    }

    private function userModel(): string
    {
        $manual = 'Add "use HasRoles;" to '.self::USER.' (Spatie\Permission\Traits\HasRoles).';
        $path = app_path('Models/User.php');

        if (! $this->files->exists($path)) {
            return $manual;
        }

        $source = $this->files->get($path);
        $edited = UserModelEditor::addHasRoles($source);

        if ($edited === null) {
            return $manual;
        }

        if ($edited === $source) {
            return 'already set up: HasRoles on '.self::USER;
        }

        $this->files->put($path, $edited);

        return 'added HasRoles to '.self::USER;
    }
}
