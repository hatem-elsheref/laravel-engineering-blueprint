<?php

declare(strict_types=1);

namespace Best\Blueprint\Testing;

use Best\Blueprint\Support\RouteAuth;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\Sanctum;
use RuntimeException;

/**
 * Generated API CRUD tests call actAsApiUser() so they work with auth:sanctum on routes.
 */
trait ActsAsApiUser
{
    /** Set true in generated tests when routes use auth:sanctum (make:crud --auth=sanctum or blueprint.route_auth). */
    public bool $lebApiSanctum = false;

    /**
     * @return $this
     */
    protected function actAsApiUser(Authenticatable $user): static
    {
        if ($this->shouldActAsSanctum()) {
            if (! class_exists(Sanctum::class)) {
                throw new RuntimeException(
                    'API routes use auth:sanctum but laravel/sanctum is not installed. Run: composer require laravel/sanctum'
                );
            }

            Sanctum::actingAs($user);

            return $this;
        }

        $this->actingAs($user);

        return $this;
    }

    private function shouldActAsSanctum(): bool
    {
        if ($this->lebApiSanctum) {
            return true;
        }

        return RouteAuth::fromConfig() === 'auth:sanctum';
    }
}
