<?php

namespace Best\Blueprint\Support;

use RuntimeException;

final class StubException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $path = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }
}
