<?php

namespace Best\Blueprint\Support;

class PestDetector
{
    public function installed(): bool
    {
        return class_exists(\Pest\TestSuite::class);
    }
}
