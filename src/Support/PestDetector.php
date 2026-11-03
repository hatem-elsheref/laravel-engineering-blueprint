<?php

namespace Best\Blueprint\Support;

use Pest\TestSuite;

class PestDetector
{
    public function installed(): bool
    {
        return class_exists(TestSuite::class);
    }
}
