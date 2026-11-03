<?php

declare(strict_types=1);

namespace Best\Blueprint\Schema;

use InvalidArgumentException;

final class SchemaException extends InvalidArgumentException
{
    public static function invalid(string $message): self
    {
        return new self("Invalid schema: {$message}.");
    }
}
