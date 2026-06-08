<?php
declare(strict_types=1);

namespace App\Core;

class Database
{
    private static ?\PDO $pdo = null;

    public static function connect(): \PDO
    {
        if (self::$pdo === null) {
            self::loadEnv();
            
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $db_name = getenv('DB_DATABASE') ?: 'sistema_soporte';
            $username = getenv('DB_USERNAME') ?: 'postgres';
            $password = getenv('DB_PASSWORD');
            if (empty($password)) {
                throw new \RuntimeException('DB_PASSWORD no está configurada en .env');
            }

            try {
                $dsn = "pgsql:host=$host;port=$port;dbname=$db_name";

                self::$pdo = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_TIMEOUT => 5
                ]);

                self::$pdo->exec("SET client_encoding TO 'UTF8'");
                self::$pdo->exec("SET search_path TO oti, admin");

            } catch (\PDOException $e) {
                error_log($e->getMessage());
                die("Error de conexion a la base de datos");
            }
        }

        return self::$pdo;
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    private static function loadEnv(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
        }
    }
}
