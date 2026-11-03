<?php

namespace Best\Blueprint\Features;

use Best\Blueprint\Flags\Definitions;
use InvalidArgumentException;

final class UnknownFeature extends InvalidArgumentException
{
    public static function key(string $key): self
    {
        return new self("Unknown feature \"{$key}\". Add it to ".Definitions::features().' first.');
    }
}
