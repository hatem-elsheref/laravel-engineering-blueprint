<?php

declare(strict_types=1);

namespace Best\Blueprint\Console;

use Best\Blueprint\Architecture\ModuleBoundaryScanner;
use Best\Blueprint\PhpStan\ModuleBoundaryPolicyFactory;
use Best\Blueprint\Support\Modules;
use Illuminate\Console\Command;

class ArchitectureCheckCommand extends Command
{
    protected $signature = 'engineering:architecture
        {--path= : Scan this directory instead of blueprint.module_path}';

    protected $description = 'Check module boundary rules (cross-module imports)';

    public function handle(Modules $modules): int
    {
        if (($error = $modules->profileError()) !== null) {
            return $this->refuse($error);
        }

        if (($error = $modules->pathError()) !== null) {
            return $this->refuse($error);
        }

        $root = $this->option('path') !== null
            ? base_path((string) $this->option('path'))
            : base_path($modules->path());

        if (! is_dir($root)) {
            $this->info('No module directory to scan.');

            return self::SUCCESS;
        }

        $scanner = new ModuleBoundaryScanner($modules, ModuleBoundaryPolicyFactory::fromApplication($modules));

        $violations = $scanner->scan($root);

        if ($violations === []) {
            $this->info('No module boundary violations found.');

            return self::SUCCESS;
        }

        foreach ($violations as $violation) {
            $this->line('<fg=red>✗</> '.$violation->message());
        }

        $this->newLine();
        $this->line('See docs/MODULE-BOUNDARIES.md and run after module changes (CI, agents, pre-merge).');

        return self::FAILURE;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
