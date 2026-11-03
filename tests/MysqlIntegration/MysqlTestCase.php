<?php

declare(strict_types=1);

namespace Best\Blueprint\Tests\MysqlIntegration;

use Best\Blueprint\Tests\TestCase;

abstract class MysqlTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        if (MysqlEnvironment::enabled()) {
            MysqlEnvironment::apply($app);
        }
    }

    protected function setUp(): void
    {
        if (! MysqlEnvironment::enabled()) {
            $this->markTestSkipped('MySQL integration tests require LEB_MYSQL=1 (see docs/TESTING.md).');
        }

        if (! MysqlEnvironment::isReachable()) {
            if (MysqlEnvironment::runningInCi()) {
                $this->fail('MySQL is required when LEB_MYSQL=1 in CI (service container or local server).');
            }

            $this->markTestSkipped('MySQL is not reachable; start MySQL or run the CI mysql-integration job.');
        }

        parent::setUp();
    }
}
