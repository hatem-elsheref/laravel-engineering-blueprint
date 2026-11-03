<?php

namespace Best\Blueprint\Features;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Thrown when a disabled feature is used; renders as HTTP 403. */
final class FeatureDisabled extends AccessDeniedHttpException
{
    public static function key(string $key): self
    {
        return new self("This feature is not enabled: {$key}.");
    }
}
