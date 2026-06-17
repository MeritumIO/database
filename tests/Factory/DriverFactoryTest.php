<?php

namespace Meritum\Database\Test\Factory;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Georgeff\Database\Connection\MySqlDriver;
use Georgeff\Database\Connection\PgsqlDriver;
use Georgeff\Database\Connection\SqliteDriver;
use Meritum\Database\Factory\DriverFactory;

class DriverFactoryTest extends TestCase
{
    private function makeContainer(array $config): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('kernel.config')->willReturn($config);
        return $container;
    }

    private function baseConfig(string $driver): array
    {
        return [
            'db.driver'        => $driver,
            'db.host'          => 'localhost',
            'db.read.hosts'    => [],
            'db.port'          => '5432',
            'db.database'      => 'mydb',
            'db.username'      => 'user',
            'db.password'      => 'secret',
            'db.sticky.write'  => false,
            'db.pgsql.schema'  => 'public',
            'db.pgsql.sslmode' => 'prefer',
            'db.mysql.charset' => 'utf8mb4',
        ];
    }

    #[Test]
    public function test_returns_pgsql_driver(): void
    {
        $driver = (new DriverFactory())($this->makeContainer($this->baseConfig('pgsql')));

        $this->assertInstanceOf(PgsqlDriver::class, $driver);
    }

    #[Test]
    public function test_returns_mysql_driver(): void
    {
        $config = array_merge($this->baseConfig('mysql'), ['db.port' => '3306']);
        $driver = (new DriverFactory())($this->makeContainer($config));

        $this->assertInstanceOf(MySqlDriver::class, $driver);
    }

    #[Test]
    public function test_returns_sqlite_driver(): void
    {
        $config = array_merge($this->baseConfig('sqlite'), ['db.database' => ':memory:']);
        $driver = (new DriverFactory())($this->makeContainer($config));

        $this->assertInstanceOf(SqliteDriver::class, $driver);
    }

    #[Test]
    public function test_driver_matching_is_case_insensitive(): void
    {
        $driver = (new DriverFactory())($this->makeContainer($this->baseConfig('PGSQL')));

        $this->assertInstanceOf(PgsqlDriver::class, $driver);
    }

    #[Test]
    public function test_throws_for_invalid_driver(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid database driver [oracle]');

        (new DriverFactory())($this->makeContainer($this->baseConfig('oracle')));
    }
}
