<?php

namespace Meritum\Database;

use Georgeff\Kernel\Support\Env;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\KernelInterface;
use Psr\Container\ContainerInterface;
use Georgeff\Database\DatabaseManager;
use Meritum\Database\Factory\DriverFactory;
use Georgeff\Database\Connection\DriverInterface;
use Georgeff\Database\Connection\ConnectionManager;
use Georgeff\Kernel\Module\ConfigurableModuleInterface;
use Georgeff\Database\Contract\DatabaseManagerInterface;
use Georgeff\Database\Contract\ConnectionManagerInterface;

final class DatabaseModule implements ConfigurableModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        $kernel->define(DriverInterface::class, new DriverFactory());

        $kernel->define(
            ConnectionManagerInterface::class,
            fn(ContainerInterface $c) => new ConnectionManager($c->get(DriverInterface::class))
        )->share();

        $kernel->define(
            DatabaseManagerInterface::class,
            fn(ContainerInterface $c) => new DatabaseManager($c->get(ConnectionManagerInterface::class))
        );
    }

    public function config(Environment $env): array
    {
        return [
            'db.driver'        => Env::get('DB_DRIVER', ''),
            'db.host'          => Env::get('DB_HOST', ''),
            'db.read.hosts'    => Env::get('DB_READ_HOSTS', []),
            'db.port'          => Env::get('DB_PORT', ''),
            'db.database'      => Env::get('DB_DATABASE', ''),
            'db.username'      => Env::get('DB_USERNAME', ''),
            'db.password'      => Env::get('DB_PASSWORD', ''),
            'db.sticky.write'  => Env::get('DB_STICKY_WRITE', true),

            'db.pgsql.schema'  => Env::get('DB_PGSQL_SCHEMA', 'public'),
            'db.pgsql.sslmode' => Env::get('DB_PGSQL_SSL_MODE', 'prefer'),

            'db.mysql.charset' => Env::get('DB_MYSQL_CHARSET', 'utf8mb4'),
        ];
    }
}
