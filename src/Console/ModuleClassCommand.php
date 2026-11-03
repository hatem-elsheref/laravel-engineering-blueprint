<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\FileWriter;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PhpSource;
use Best\Blueprint\Support\PhpNames;
use Best\Blueprint\Support\StubException;
use Best\Blueprint\Support\StubRenderer;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

/**
 * Shared flow for generators that add one class to an existing module.
 */
abstract class ModuleClassCommand extends Command
{
    /** Folder inside the module, e.g. "Data". */
    abstract protected function folder(): string;

    abstract protected function stub(): string;

    /** Example class name for the invalid-name message. */
    abstract protected function example(): string;

    /**
     * Stub values other than namespace and class. Return a string to refuse with that message.
     *
     * @return array<string, string>|string
     */
    abstract protected function values(string $module, string $moduleNamespace, string $class): array|string;

    abstract protected function tip(string $class): ?string;

    public function handle(Filesystem $files, StubRenderer $stubs): int
    {
        $modules = new Modules;

        if (($error = $modules->profileError() ?? $modules->pathError()) !== null) {
            return $this->refuse($error);
        }

        $moduleInput = (string) $this->argument('module');
        [$module, $moduleError] = $modules->locate($moduleInput);

        if ($moduleError !== null) {
            return $this->refuse($moduleError);
        }

        $input = (string) $this->argument('name');
        $class = ModuleName::normalize($input);

        if ($class === null || PhpNames::isReserved($class)) {
            return $this->refuse("Invalid class name \"{$input}\". Use letters and numbers, starting with a letter, e.g. {$this->example()}.");
        }

        if ($class !== $input) {
            $this->line("Using class name \"{$class}\".");
        }

        $folder = $modules->path().'/'.$module.'/'.$this->folder();

        if (($existing = Modules::entry(base_path($folder), $class.'.php')) !== null) {
            return $this->refuse(sprintf('"%s" already exists at %s/%s.', pathinfo($existing, PATHINFO_FILENAME), $folder, $existing));
        }

        $moduleNamespace = $modules->namespace().'\\'.$module;
        $namespace = $moduleNamespace.'\\'.$this->folder();
        $values = $this->values($module, $moduleNamespace, $class);

        if (is_string($values)) {
            return $this->refuse($values);
        }

        try {
            $contents = $stubs->render($this->stub(), ['namespace' => $namespace, 'class' => $class] + $values);
        } catch (StubException $e) {
            return $this->refuse($this->invalidStub($e->path, $e->reason));
        }

        if (! PhpSource::declares($contents, $namespace, $class)) {
            return $this->refuse($this->invalidStub($stubs->path($this->stub()), "no {$class} class in namespace {$namespace}"));
        }

        try {
            (new FileWriter($files))->write([base_path("{$folder}/{$class}.php") => $contents]);
        } catch (Throwable $e) {
            return $this->refuse("Could not write {$folder}/{$class}.php ({$e->getMessage()}). Nothing was changed.");
        }

        $this->line("created {$folder}/{$class}.php");

        if (($tip = $this->tip($class)) !== null) {
            $this->line($tip);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $classes
     */
    protected function imports(array $classes): string
    {
        $classes = array_unique($classes);
        sort($classes);

        return $classes === [] ? '' : implode('', array_map(fn ($c) => "use {$c};\n", $classes))."\n";
    }

    private function invalidStub(?string $path, ?string $reason): string
    {
        return "Stub {$path} did not produce a valid class ({$reason}). Update or delete it, then re-run.";
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
