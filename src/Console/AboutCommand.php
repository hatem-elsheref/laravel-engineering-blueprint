<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Blueprint;
use Best\Blueprint\Support\Compatibility;
use Illuminate\Console\Command;

class AboutCommand extends Command
{
    protected $signature = 'engineering:about';

    protected $description = 'Show Laravel Engineering Blueprint version and environment compatibility';

    private const LABELS = ['php' => 'PHP', 'laravel' => 'Laravel'];

    public function handle(Compatibility $compatibility): int
    {
        $this->line(Blueprint::NAME.' '.Blueprint::version());

        $result = $compatibility->check();

        foreach ($result->checks as $check) {
            $this->line(sprintf(
                '%s %s >= %s (actual %s)',
                $check['passed'] ? '✓' : '✗',
                self::LABELS[$check['name']] ?? $check['name'],
                $check['required'],
                $check['actual'],
            ));
        }

        if (! $result->passed) {
            $this->error('Environment is not compatible: upgrade the items marked ✗.');

            return self::FAILURE;
        }

        $this->info('Environment is compatible.');

        return self::SUCCESS;
    }
}
