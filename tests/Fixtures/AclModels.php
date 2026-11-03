<?php

namespace Best\Blueprint\Tests\Fixtures;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** An app's own permission model (spatie's permission.models.permission). Records what it creates. */
class AclPermission extends Permission
{
    /** @var list<string> */
    public static array $created = [];

    protected static function booted(): void
    {
        static::created(fn (self $permission) => static::$created[] = $permission->name);
    }
}

/** An app's own role model (spatie's permission.models.role). Records what it creates. */
class AclRole extends Role
{
    /** @var list<string> */
    public static array $created = [];

    protected static function booted(): void
    {
        static::created(fn (self $role) => static::$created[] = $role->name);
    }
}
