<?php

namespace Best\Blueprint\Support;

use InvalidArgumentException;

final class FieldException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $entry,
        public readonly string $reason,
    ) {
        parent::__construct("Invalid field \"{$entry}\": {$reason}.");
    }
}
