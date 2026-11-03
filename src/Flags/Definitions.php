<?php

namespace Best\Blueprint\Flags;

/**
 * Where the feature and setting definition classes live: the blueprint.features / blueprint.settings
 * config value when set, otherwise derived from blueprint.shared_path (what engineering:install generates).
 */
final class Definitions
{
    public static function features(): string
    {
        return self::resolve('features', 'Features\\Features');
    }

    public static function settings(): string
    {
        return self::resolve('settings', 'Settings\\Settings');
    }

    public static function permissions(): string
    {
        return self::resolve('permissions', 'Permissions\\Permissions');
    }

    public static function centralPermissions(): string
    {
        return self::resolve('central_permissions', 'Permissions\\CentralPermissions');
    }

    /** Namespace of blueprint.shared_path, or null when it isn't inside app/. */
    public static function sharedNamespace(): ?string
    {
        $path = trim((string) config('blueprint.shared_path', 'app/Shared'), '/');

        if (preg_match('#^app(/[A-Za-z_][A-Za-z0-9_]*)+$#', $path) !== 1) {
            return null;
        }

        return 'App\\'.str_replace('/', '\\', substr($path, strlen('app/')));
    }

    private static function resolve(string $key, string $suffix): string
    {
        $configured = config("blueprint.{$key}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (self::sharedNamespace() ?? 'App\\Shared').'\\'.$suffix;
    }
}
