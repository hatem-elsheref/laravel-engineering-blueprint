<?php

namespace Best\Blueprint\Tenancy;

use RuntimeException;
use Throwable;

final class TenantSetupException extends RuntimeException
{
    /**
     * @param  list<array{step: string, status: string, note: ?string}>  $completed  steps finished before the failure
     */
    public function __construct(public readonly string $step, Throwable $previous, public readonly array $completed = [])
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
