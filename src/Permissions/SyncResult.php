<?php

namespace Best\Blueprint\Permissions;

final readonly class SyncResult
{
    /** @param  list<string>  $stale  Permission names, and roles as "role:<name>"; never the super-admin. */
    public function __construct(
        public int $permissionsAdded,
        public int $rolesAdded,
        public array $stale,
        public bool $pruned,
    ) {}
}
