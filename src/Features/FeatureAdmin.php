<?php

namespace Best\Blueprint\Features;

use Best\Blueprint\Flags\AdminGuard;
use Best\Blueprint\Flags\Rows;
use Illuminate\Support\Facades\DB;

/**
 * The only way to change feature states: platform-admin code (console commands), never HTTP,
 * and in tenant-saas only inside a tenant.
 */
class FeatureAdmin
{
    public function __construct(
        private readonly FeatureManager $features,
        private readonly AdminGuard $guard,
    ) {}

    public function enable(string $key): void
    {
        $this->set($key, true);
    }

    public function disable(string $key): void
    {
        $this->set($key, false);
    }

    /**
     * Adds a row (default state) for every defined feature that has none; existing rows are kept.
     */
    public function sync(): int
    {
        $this->guard->check();
        $existing = DB::table('features')->pluck('key')->all();
        $added = 0;

        foreach ($this->features->definitions() as $key => $default) {
            if (! in_array($key, $existing, true)) {
                DB::table('features')->insert(['key' => $key, 'enabled' => $default, 'created_at' => now(), 'updated_at' => now()]);
                $added++;
            }
        }

        $this->features->forget();

        return $added;
    }

    private function set(string $key, bool $enabled): void
    {
        $this->guard->check();
        $this->features->assertDefined($key);

        Rows::upsert('features', $key, ['enabled' => $enabled]);
        $this->features->forget();
    }
}
