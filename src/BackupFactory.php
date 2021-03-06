<?php

namespace Lzakrzewski\DoctrineDatabaseBackup;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManager;
use Lzakrzewski\DoctrineDatabaseBackup\Backup\MySqlBackup;
use Lzakrzewski\DoctrineDatabaseBackup\Backup\SqliteBackup;
use Lzakrzewski\DoctrineDatabaseBackup\Command\MysqldumpCommand;
use Lzakrzewski\DoctrineDatabaseBackup\Storage\InMemoryStorage;
use Lzakrzewski\DoctrineDatabaseBackup\Storage\LocalStorage;

final class BackupFactory
{
    public static function instance(EntityManager $entityManager)
    {
        $connection = $entityManager->getConnection();

        if ($connection->getDatabasePlatform() instanceof SqlitePlatform) {
            return self::sqliteBackup($entityManager);
        }

        if ($connection->getDatabasePlatform() instanceof MySqlPlatform) {
            return self::mySqlBackup($entityManager);
        }

        throw new \RuntimeException('Unsupported database platform. Currently "SqlitePlatform" is supported.');
    }

    private static function sqliteBackup(EntityManager $entityManager)
    {
        $params = $entityManager->getConnection()->getParams();

        if (false === isset($params['path']) || $params['path'] == ':memory:') {
            throw new \RuntimeException('Backup for Sqlite "in_memory" is not supported.');
        }

        return new SqliteBackup($params['path'], InMemoryStorage::instance(), new LocalStorage());
    }

    private static function mySqlBackup(EntityManager $entityManager)
    {
        $params = $entityManager->getConnection()->getParams();

        if (false === isset($params['dbname'])) {
            throw new \RuntimeException('Database name should be provided');
        }

        $host     = (isset($params['host'])) ? $params['host'] : null;
        $port     = (isset($params['port'])) ? $params['port'] : null;
        $user     = (isset($params['user'])) ? $params['user'] : null;
        $password = (isset($params['password'])) ? $params['password'] : null;

        $purger  = PurgerFactory::instance($entityManager);
        $command = new MysqldumpCommand($params['dbname'], $host, $port, $user, $password);

        return new MySqlBackup($entityManager->getConnection(), InMemoryStorage::instance(), $purger, $command);
    }

    private function __construct()
    {
    }
}
