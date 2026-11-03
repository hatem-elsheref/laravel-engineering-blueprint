<?php

namespace Best\Blueprint\Tenancy;

use RuntimeException;
use Throwable;

final class TenantSetupException extends RuntimeException
{
    public function __construct(public readonly string $step, Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
