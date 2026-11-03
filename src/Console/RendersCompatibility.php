<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Support\CompatibilityResult;

trait RendersCompatibility
{
    protected function renderCompatibility(CompatibilityResult $result): void
    {
        $labels = ['php' => 'PHP', 'laravel' => 'Laravel'];

        foreach ($result->checks as $check) {
            $this->line(sprintf(
                '%s %s >= %s (actual %s)',
                $check['passed'] ? '✓' : '✗',
                $labels[$check['name']] ?? $check['name'],
                $check['required'],
                $check['actual'],
            ));
        }
    }
}
