<?php

use Best\Blueprint\Tests\MysqlIntegration\MysqlEnvironment;
use Best\Blueprint\Tests\MysqlIntegration\MysqlTestCase;

uses(MysqlTestCase::class);

it('requires a reachable mysql server when LEB_MYSQL is enabled in ci', function (): void {
    expect(MysqlEnvironment::enabled())->toBeTrue()
        ->and(MysqlEnvironment::isReachable())->toBeTrue();
});
