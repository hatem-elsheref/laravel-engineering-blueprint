<?php

namespace Best\Blueprint\Tests\Fixtures;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecordingTenantSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('records')->insert(['tenant' => tenant('id')]);
    }
}
