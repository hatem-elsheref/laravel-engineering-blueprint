<?php

namespace Best\Blueprint\Support;

use Best\Blueprint\Permissions\CrudPermissions;

/**
 * Detects which make:crud policy mode an existing policy file uses when adding a side.
 */
final class GeneratedPolicy
{
    public const DENY_MARKER = 'Blueprint: deny-by-default policy.';

    public const OPEN_MARKER = 'Blueprint: open policy (--open-policy).';

    public static function isPermissionsMode(string $policySource): bool
    {
        return str_contains($policySource, CrudPermissions::POLICY_MARKER);
    }

    public static function isOpen(string $policySource): bool
    {
        return str_contains($policySource, self::OPEN_MARKER);
    }

    public static function isDenyByDefault(string $policySource): bool
    {
        return str_contains($policySource, self::DENY_MARKER);
    }

    public static function stub(bool $permissions, bool $owned, bool $openPolicy): string
    {
        return match (true) {
            $owned => 'policy-owned.stub',
            $permissions => 'policy-permissions.stub',
            $openPolicy => 'policy-open.stub',
            default => 'policy.stub',
        };
    }
}
