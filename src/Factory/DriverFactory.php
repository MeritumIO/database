<?php

namespace Meritum\Database\Factory;

use Psr\Container\ContainerInterface;
use Georgeff\Database\Connection\PgsqlDriver;
use Georgeff\Database\Connection\MySqlDriver;
use Georgeff\Database\Connection\SqliteDriver;
use Georgeff\Database\Connection\DriverInterface;

final class DriverFactory
{
    public function __invoke(ContainerInterface $container): DriverInterface
    {
        /**
         * @var array{
         *  'db.driver': string,
         *  'db.host': string,
         *  'db.read.hosts': string[],
         *  'db.port': string,
         *  'db.database': string,
         *  'db.username': string,
         *  'db.password': string,
         *  'db.sticky.write': bool,
         *  'db.pgsql.schema': string,
         *  'db.pgsql.sslmode': string,
         *  'db.mysql.charset': string
         * } $config
         */
        $config = $container->get('kernel.config');

        $driver = strtolower($config['db.driver']);

        return match ($driver) {
            'pgsql'  => $this->getPgsqlDriver($config),
            'mysql'  => $this->getMySqlDriver($config),
            'sqlite' => $this->getSqliteDriver($config),
            default  => throw new \RuntimeException("Invalid database driver [{$driver}]")
        };
    }

    /**
     * @param array{'db.database': string} $config
     */
    private function getSqliteDriver(array $config): SqliteDriver
    {
        return new SqliteDriver($config['db.database']);
    }

    /**
     * @param array{
     *      'db.host': string,
     *      'db.read.hosts': string[],
     *      'db.port': string,
     *      'db.database': string,
     *      'db.username': string,
     *      'db.password': string,
     *      'db.sticky.write': bool,
     *      'db.pgsql.schema': string,
     *      'db.pgsql.sslmode': string
     * } $config
     */
    private function getPgsqlDriver(array $config): PgsqlDriver
    {
        return new PgsqlDriver(
            host: $config['db.host'],
            readHosts: $config['db.read.hosts'],
            port: $config['db.port'],
            database: $config['db.database'],
            username: $config['db.username'],
            password: $config['db.password'],
            schema: $config['db.pgsql.schema'],
            sslmode: $config['db.pgsql.sslmode'],
            sticky: $config['db.sticky.write']
        );
    }

    /**
     * @param array{
     *      'db.host': string,
     *      'db.read.hosts': string[],
     *      'db.port': string,
     *      'db.database': string,
     *      'db.username': string,
     *      'db.password': string,
     *      'db.sticky.write': bool,
     *      'db.mysql.charset': string
     * } $config
     */
    private function getMySqlDriver(array $config): MySqlDriver
    {
        return new MySqlDriver(
            host: $config['db.host'],
            readHosts: $config['db.read.hosts'],
            port: $config['db.port'],
            database: $config['db.database'],
            username: $config['db.username'],
            password: $config['db.password'],
            charset: $config['db.mysql.charset'],
            sticky: $config['db.sticky.write']
        );
    }
}
