<?php

namespace Best\Blueprint\Flags;

use Illuminate\Support\Facades\DB;

/**
 * @internal Row writes for feature/setting tables; callers do the guarding (FeatureAdmin, SettingAdmin,
 *           SettingManager::set). Not reachable through the Feature/Setting facades.
 */
final class Rows
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function upsert(string $table, string $key, array $values): void
    {
        $now = now();

        if (DB::table($table)->where('key', $key)->exists()) {
            DB::table($table)->where('key', $key)->update($values + ['updated_at' => $now]);
        } else {
            DB::table($table)->insert(['key' => $key] + $values + ['created_at' => $now, 'updated_at' => $now]);
        }
    }
}
