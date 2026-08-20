<?php

declare(strict_types=1);

namespace App;

final class Database
{
    private static ?\PDO $connection = null;

    public static function connect(array $config): \PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $db = $config['db'];
        if ($db['driver'] === 'mysql') {
            $dsn = "mysql:host={$db['mysql_host']};dbname={$db['mysql_name']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $db['mysql_user'], $db['mysql_pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } else {
            $dir = dirname($db['sqlite_path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $pdo = new \PDO('sqlite:' . $db['sqlite_path'], null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        self::$connection = $pdo;
        return $pdo;
    }
}
