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
    $credentials = [];

    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD')) {
        $credentials[] = [
            'host'   => DB_HOST,
            'dbname' => DB_NAME,
            'user'   => DB_USER,
            'pass'   => DB_PASSWORD,
        ];
    }

    $credentials[] = [
        'host'   => 'db50000543572.hosting-data.io',
        'dbname' => 'dbs521868',
        'user'   => 'dbo50000543572',
        'pass'   => '14Juillet@',
    ];

    $seenConfigs = [];

    $lastException = null;

    foreach ($credentials as $config) {
        $hostKey = sprintf('%s|%s|%s', $config['host'], $config['dbname'], $config['user']);
        if (isset($seenConfigs[$hostKey])) {
            continue;
        }
        $seenConfigs[$hostKey] = true;

        [$host, $port, $socket] = inventory_normalize_host($config['host']);

        try {
            $dsn = sprintf('mysql:dbname=%s;charset=%s', $config['dbname'], $charset);
            if ($socket !== null) {
                $dsn .= sprintf(';unix_socket=%s', $socket);
            } else {
                $dsn .= sprintf(';host=%s', $host);
                if ($port !== null) {
                    $dsn .= sprintf(';port=%d', $port);
                }
            }
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

/**
 * Normalise une valeur d'hôte WordPress en composantes host/port/socket.
 */
function inventory_normalize_host(string $host): array
{
    $host = trim($host);
    $port = null;
    $socket = null;

    if ($host === '') {
        return ['localhost', null, null];
    }

    // Gestion des sockets de type "localhost:/chemin/socket".
    if (strpos($host, ':') !== false) {
        $parts = explode(':', $host, 2);
        if (isset($parts[1]) && $parts[1] !== '') {
            if ($parts[1][0] === '/') {
                $socket = $parts[1];
                $host = $parts[0] === '' ? 'localhost' : $parts[0];
            } elseif (ctype_digit($parts[1])) {
                $port = (int) $parts[1];
                $host = $parts[0];
            }
        }
    }

    return [$host, $port, $socket];
}
