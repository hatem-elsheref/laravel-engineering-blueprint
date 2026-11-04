<?php

declare(strict_types=1);

namespace Best\Blueprint\Tests\MysqlIntegration;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\Schema;

final class MysqlEnvironment
{
    public static function freshCentralTenancySchema(LaravelTestCase $test): void
    {
        $path = realpath(__DIR__.'/../../vendor/stancl/tenancy/assets/migrations');

        if ($path === false) {
            $test->fail('Stancl tenancy migrations directory is missing.');
        }

        $test->artisan('migrate:fresh', [
            '--database' => 'testing',
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ])->assertExitCode(0);

        if (! Schema::connection('testing')->hasTable('tenants')) {
            $test->fail('Central tenants table missing after stancl migrate:fresh.');
        }
    }

    public static function enabled(): bool
    {
        return filter_var(getenv('LEB_MYSQL') ?: '', FILTER_VALIDATE_BOOL);
    }

    public static function runningInCi(): bool
    {
        return filter_var(getenv('GITHUB_ACTIONS') ?: '', FILTER_VALIDATE_BOOL)
            || filter_var(getenv('CI') ?: '', FILTER_VALIDATE_BOOL);
    }

    public static function isReachable(): bool
    {
        $host = getenv('LEB_MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('LEB_MYSQL_PORT') ?: '3306';
        $database = getenv('LEB_MYSQL_DATABASE') ?: 'leb_central';
        $username = getenv('LEB_MYSQL_USERNAME') ?: 'root';
        $password = getenv('LEB_MYSQL_PASSWORD') ?: '';

        try {
            new \PDO(
                "mysql:host={$host};port={$port};dbname={$database}",
                $username,
                $password,
                [\PDO::ATTR_TIMEOUT => 2],
            );

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public static function apply(Application $app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
            'host' => getenv('LEB_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('LEB_MYSQL_PORT') ?: '3306',
            'database' => getenv('LEB_MYSQL_DATABASE') ?: 'leb_central',
            'username' => getenv('LEB_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('LEB_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }
}
