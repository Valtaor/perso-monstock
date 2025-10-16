<?php
/**
 * Database connection helper for the inventory application.
 *
 * The connection step is wrapped in a try/catch so the caller can decide how to
 * surface an eventual failure rather than letting PHP die with a raw message.
 */

declare(strict_types=1);

$host = 'db50000543572.hosting-data.io';
$user = 'dbo50000543572';
$pass = '14Juillet@';
$dbname = 'dbs521868';

$pdo = null;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8', $host, $dbname),
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $exception) {
    $pdo = null;

    if (function_exists('error_log')) {
        error_log('[Inventory] Connexion base impossible : ' . $exception->getMessage());
    }
}
