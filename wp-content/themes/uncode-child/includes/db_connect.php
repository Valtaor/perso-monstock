<?php
/**
 * Database connection helper for the inventory application.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve a PDO instance connected to the inventory database.
 */
function inventory_acquire_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $charset = 'utf8mb4';
    $credentials = [
        [
            'host' => 'db50000543572.hosting-data.io',
            'dbname' => 'dbs521868',
            'user' => 'dbo50000543572',
            'pass' => '14Juillet@',
        ],
    ];

    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
        $credentials[] = [
            'host' => DB_HOST,
            'dbname' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASSWORD,
        ];
    }

    $lastException = null;

    foreach ($credentials as $config) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['dbname'], $charset);
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            return $pdo;
        } catch (PDOException $exception) {
            $lastException = $exception;
        }
    }

    if ($lastException instanceof PDOException) {
        throw $lastException;
    }

    throw new RuntimeException('Impossible de se connecter à la base de données.');
}
