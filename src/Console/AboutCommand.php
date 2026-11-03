<?php

namespace Best\Blueprint\Console;

use Best\Blueprint\Blueprint;
use Best\Blueprint\Support\Compatibility;
use Illuminate\Console\Command;

class AboutCommand extends Command
{
    use RendersCompatibility;

    protected $signature = 'engineering:about';

    protected $description = 'Show Laravel Engineering Blueprint version and environment compatibility';

    public function handle(Compatibility $compatibility): int
    {
        $this->line(Blueprint::NAME.' '.Blueprint::version());

        $result = $compatibility->check();

        $this->renderCompatibility($result);

        if (! $result->passed) {
            $this->error('Environment is not compatible: upgrade the items marked ✗.');

            return self::FAILURE;
        }

        $this->info('Environment is compatible.');

        return self::SUCCESS;
    }
}
