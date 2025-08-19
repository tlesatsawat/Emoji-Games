<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Simple PDO wrapper for connecting to PostgreSQL using a DATABASE URL.
 */
class DB
{
    private static ?PDO $pdo = null;

    /**
     * Initialise the connection if not already connected.  Reads the
     * `DB_URL` environment variable and constructs a DSN for PDO.
     */
    public static function init(): void
    {
        if (self::$pdo !== null) {
            return;
        }
        $url = $_ENV['DB_URL'] ?? getenv('DB_URL');
        if (!$url) {
            throw new \RuntimeException('DB_URL must be set in your environment');
        }
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \RuntimeException('Invalid DB_URL');
        }
        $driver = $parts['scheme'] ?? 'pgsql';
        $user = $parts['user'] ?? null;
        $pass = $parts['pass'] ?? null;
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? 5432;
        $dbname = ltrim($parts['path'] ?? '', '/');
        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $dbname);
        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Failed to connect to database: ' . $e->getMessage());
        }
    }

    /**
     * Get the PDO instance.  Initialises the connection if required.
     */
    public static function pdo(): PDO
    {
        self::init();
        return self::$pdo;
    }
}
