<?php

namespace Meritum\Database\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Georgeff\Database\Connection\DriverInterface;
use Georgeff\Database\Contract\ConnectionManagerInterface;
use Georgeff\Database\Contract\DatabaseManagerInterface;
use Georgeff\Kernel\DI\DefinitionInterface;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\KernelInterface;
use Meritum\Database\DatabaseModule;

class DatabaseModuleTest extends TestCase
{
    #[Test]
    public function test_config_returns_all_expected_keys(): void
    {
        $config = (new DatabaseModule())->config(Environment::Production);

        $this->assertArrayHasKey('db.driver', $config);
        $this->assertArrayHasKey('db.host', $config);
        $this->assertArrayHasKey('db.read.hosts', $config);
        $this->assertArrayHasKey('db.port', $config);
        $this->assertArrayHasKey('db.database', $config);
        $this->assertArrayHasKey('db.username', $config);
        $this->assertArrayHasKey('db.password', $config);
        $this->assertArrayHasKey('db.sticky.write', $config);
        $this->assertArrayHasKey('db.pgsql.schema', $config);
        $this->assertArrayHasKey('db.pgsql.sslmode', $config);
        $this->assertArrayHasKey('db.mysql.charset', $config);
    }

    #[Test]
    public function test_config_defaults(): void
    {
        putenv('DB_PGSQL_SCHEMA');
        putenv('DB_PGSQL_SSL_MODE');
        putenv('DB_MYSQL_CHARSET');
        putenv('DB_STICKY_WRITE');
        putenv('DB_READ_HOSTS');

        $config = (new DatabaseModule())->config(Environment::Production);

        $this->assertSame('public', $config['db.pgsql.schema']);
        $this->assertSame('prefer', $config['db.pgsql.sslmode']);
        $this->assertSame('utf8mb4', $config['db.mysql.charset']);
        $this->assertTrue($config['db.sticky.write']);
        $this->assertSame([], $config['db.read.hosts']);
    }

    #[Test]
    public function test_config_reads_env_vars(): void
    {
        putenv('DB_DRIVER=mysql');
        putenv('DB_HOST=db.example.com');
        putenv('DB_PORT=3307');
        putenv('DB_DATABASE=myapp');
        putenv('DB_USERNAME=appuser');
        putenv('DB_PASSWORD=s3cret');

        try {
            $config = (new DatabaseModule())->config(Environment::Production);

            $this->assertSame('mysql', $config['db.driver']);
            $this->assertSame('db.example.com', $config['db.host']);
            $this->assertSame('3307', $config['db.port']);
            $this->assertSame('myapp', $config['db.database']);
            $this->assertSame('appuser', $config['db.username']);
            $this->assertSame('s3cret', $config['db.password']);
        } finally {
            putenv('DB_DRIVER');
            putenv('DB_HOST');
            putenv('DB_PORT');
            putenv('DB_DATABASE');
            putenv('DB_USERNAME');
            putenv('DB_PASSWORD');
        }
    }

    #[Test]
    public function test_register_defines_driver_connection_and_manager(): void
    {
        $definedIds = [];
        $definition = $this->createStub(DefinitionInterface::class);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('define')->willReturnCallback(
            function (string $id, callable $factory) use (&$definedIds, $definition): DefinitionInterface {
                $definedIds[] = $id;
                return $definition;
            }
        );

        (new DatabaseModule())->register($kernel);

        $this->assertContains(DriverInterface::class, $definedIds);
        $this->assertContains(ConnectionManagerInterface::class, $definedIds);
        $this->assertContains(DatabaseManagerInterface::class, $definedIds);
    }
}
