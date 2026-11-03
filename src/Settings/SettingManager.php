<?php

namespace Best\Blueprint\Settings;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Flags\FlagContext;
use Best\Blueprint\Flags\Rows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Typed tenant settings (docs/SETTINGS.md). Bound as "scoped": the memo lives for one request or job.
 */
class SettingManager
{
    /** @var array<string, array<string, mixed>> context => key => decoded value */
    private array $memo = [];

    public function __construct(private readonly FlagContext $context) {}

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $context = $this->context->key();

        if ($context === null) {
            return $definition['default'];
        }

        $this->memo[$context] ??= DB::table('settings')->pluck('value', 'key')
            ->map(fn ($value) => $value === null ? null : json_decode($value, true))
            ->all();

        if (! array_key_exists($key, $this->memo[$context])) {
            return $definition['default'];
        }

        [$fits, $value] = SettingTypes::convert($definition['type'], $this->memo[$context][$key]);

        return $fits ? $value : $definition['default'];
    }

    /**
     * Tenant-side write: only "editable_by: tenant" settings, only through their Gate ability.
     */
    public function set(string $key, mixed $value): void
    {
        $definition = $this->definition($key);

        if (($definition['editable_by'] ?? 'platform') !== 'tenant') {
            throw new SettingNotEditable("Setting \"{$key}\" can only be changed by the platform.");
        }

        if (empty($definition['ability'])) {
            throw new SettingNotEditable("Setting \"{$key}\" has no ability; tenant settings must name a Gate ability to be changed.");
        }

        Gate::authorize($definition['ability']);
        $converted = $this->convert($key, $definition, $value);

        if ($this->context->key() === null) {
            throw new RuntimeException('No tenant context.');
        }

        $this->write($key, $converted);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $values = [];

        foreach (array_keys($this->definitions()) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * @return array<string, array{type: string, default: mixed, editable_by?: string, ability?: string}>
     */
    public function definitions(): array
    {
        $class = Definitions::settings();

        if (! class_exists($class)) {
            throw new RuntimeException('Setting definitions not found; run php artisan engineering:install.');
        }

        return $class::definitions();
    }

    /**
     * @return array{type: string, default: mixed, editable_by?: string, ability?: string}
     */
    public function definition(string $key): array
    {
        return $this->definitions()[$key] ?? throw new UnknownSetting("Unknown setting \"{$key}\". Add it to your Settings definitions first.");
    }

    /**
     * @param  array{type: string}  $definition
     */
    public function convert(string $key, array $definition, mixed $value): mixed
    {
        [$fits, $converted] = SettingTypes::convert($definition['type'], $value);

        if (! $fits) {
            throw new InvalidSettingValue("Setting \"{$key}\" must be of type {$definition['type']}.");
        }

        return $converted;
    }

    private function write(string $key, mixed $value): void
    {
        Rows::upsert('settings', $key, ['value' => json_encode($value)]);
        $this->forget();
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
