<?php

namespace Best\Blueprint\Tests\Fixtures;

use Best\Blueprint\Features\RequireFeature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class GatedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public static bool $ran = false;

    public function middleware(): array
    {
        return [new RequireFeature('reports')];
    }

    public function handle(): void
    {
        self::$ran = true;
    }
}
