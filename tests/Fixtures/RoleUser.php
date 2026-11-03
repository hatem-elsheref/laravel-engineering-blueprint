<?php

namespace Best\Blueprint\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class RoleUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'users';

    protected string $guard_name = 'web';

    protected $guarded = [];
}
