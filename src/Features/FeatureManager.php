<?php

namespace Best\Blueprint\Features;

use Best\Blueprint\Flags\Definitions;
use Best\Blueprint\Flags\FlagContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Read-only feature checks for application code (docs/FEATURES.md §4.4). Bound as "scoped":
 * the memo lives for one request or one queued job.
 */
class FeatureManager
{
    /** @var array<string, array<string, bool>> context => key => enabled */
    private array $memo = [];

    public function __construct(private readonly FlagContext $context) {}

    public function enabled(string $key): bool
    {
        $this->assertDefined($key);
        $context = $this->context->key();

        if ($context === null) {
            return false;
        }

        $this->memo[$context] ??= DB::table('features')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all();

        return $this->memo[$context][$key] ?? false;
    }

    public function disabled(string $key): bool
    {
        return ! $this->enabled($key);
    }

    public function ensure(string $key): void
    {
        if (! $this->enabled($key)) {
            throw FeatureDisabled::key($key);
        }
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        $states = [];

        foreach (array_keys($this->definitions()) as $key) {
            $states[$key] = $this->enabled($key);
        }

        return $states;
    }

    /**
     * @return array<string, bool> key => default state
     */
    public function definitions(): array
    {
        $class = Definitions::features();

        if (! class_exists($class)) {
            throw new RuntimeException('Feature definitions not found; run php artisan engineering:install.');
        }

        return $class::defaults();
    }

    public function assertDefined(string $key): void
    {
        if (! array_key_exists($key, $this->definitions())) {
            throw UnknownFeature::key($key);
        }
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
