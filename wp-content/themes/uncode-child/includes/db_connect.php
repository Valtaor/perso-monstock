<?php
/**
 * Database connection helper for the inventory application.
 *
 * The connection step is wrapped in a try/catch so the caller can decide how to
 * surface an eventual failure rather than letting PHP die with a raw message.
 */

declare(strict_types=1);

$defaultConfig = [
    'host' => 'db50000543572.hosting-data.io',
    'user' => 'dbo50000543572',
    'pass' => '14Juillet@',
    'name' => 'dbs521868',
    'charset' => 'utf8',
];

if (
    defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_NAME')
) {
    $defaultConfig['host'] = (string) DB_HOST;
    $defaultConfig['user'] = (string) DB_USER;
    $defaultConfig['pass'] = (string) DB_PASSWORD;
    $defaultConfig['name'] = (string) DB_NAME;
    if (defined('DB_CHARSET') && DB_CHARSET !== '') {
        $defaultConfig['charset'] = (string) DB_CHARSET;
    }
}

$pdo = null;

$dsnHost = $defaultConfig['host'];
$dsnExtras = '';
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

if (strpos($dsnHost, ':') !== false) {
    [$rawHost, $portOrSocket] = explode(':', $dsnHost, 2);
    $rawHost = trim($rawHost);
    $portOrSocket = trim($portOrSocket);

    if ($portOrSocket !== '') {
        if (ctype_digit($portOrSocket)) {
            $dsnHost = $rawHost;
            $dsnExtras = ';port=' . $portOrSocket;
        } else {
            $dsnHost = $rawHost;
            if (defined('PDO::MYSQL_ATTR_UNIX_SOCKET')) {
                $pdoOptions[PDO::MYSQL_ATTR_UNIX_SOCKET] = $portOrSocket;
            }
        }
    }
}

if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $defaultConfig['charset'];
}

$dsn = sprintf(
    'mysql:host=%s%s;dbname=%s;charset=%s',
    $dsnHost,
    $dsnExtras,
    $defaultConfig['name'],
    $defaultConfig['charset']
);

try {
    $pdo = new PDO(
        $dsn,
        $defaultConfig['user'],
        $defaultConfig['pass'],
        $pdoOptions
    );
} catch (PDOException $exception) {
    $pdo = null;

    if (function_exists('error_log')) {
        error_log('[Inventory] Connexion base impossible : ' . $exception->getMessage());
    }
}
