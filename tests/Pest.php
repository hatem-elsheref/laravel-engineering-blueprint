<?php

use Best\Blueprint\Tests\TenancyTestCase;
use Best\Blueprint\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
pest()->extend(TenancyTestCase::class)->in('Tenancy');
