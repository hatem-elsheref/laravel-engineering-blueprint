<?php

namespace Best\Blueprint\Flags;

use Best\Blueprint\Support\Profile;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Filesystem\Filesystem;

/**
 * Generates the feature/setting definition classes and their migrations (idempotent).
 */
class FlagSetup
{
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
        $namespace = 'App\\'.str_replace('/', '\\', substr($shared, strlen('app/')));
        $migrations = $profile->usesTenancy() ? 'database/migrations/tenant' : 'database/migrations';

        return [
            $this->definition("{$shared}/Features/Features.php", 'features-definitions.stub', "{$namespace}\\Features"),
            $this->definition("{$shared}/Settings/Settings.php", 'settings-definitions.stub', "{$namespace}\\Settings"),
            $this->migration($migrations, 'features', '000100'),
            $this->migration($migrations, 'settings', '000200'),
        ];
    }

    private function definition(string $relative, string $stub, string $namespace): string
    {
        if ($this->files->exists(base_path($relative))) {
            return "already set up: {$relative}";
        }

        $this->files->ensureDirectoryExists(dirname(base_path($relative)));
        $this->files->put(base_path($relative), $this->stubs->render($stub, ['namespace' => $namespace]));

        return "✓ created {$relative}";
    }

    private function migration(string $folder, string $table, string $suffix): string
    {
        if ((glob(base_path("{$folder}/*_create_{$table}_table.php")) ?: []) !== []) {
            return "already set up: {$table} migration";
        }

        $relative = "{$folder}/".date('Y_m_d_').$suffix."_create_{$table}_table.php";
        $this->files->ensureDirectoryExists(dirname(base_path($relative)));
        $this->files->put(base_path($relative), $this->stubs->render("{$table}-migration.stub", []));

        return "✓ created {$relative}";
    }
}
