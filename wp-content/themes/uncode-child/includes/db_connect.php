<?php
/**
 * Database connection for the inventory application.
 *
 * Uses the WordPress database credentials to ensure consistency between
 * the inventory dashboard and the site's main configuration.
 */

$host   = defined('DB_HOST') ? DB_HOST : 'localhost';
$user   = defined('DB_USER') ? DB_USER : '';
$pass   = defined('DB_PASSWORD') ? DB_PASSWORD : '';
$dbname = defined('DB_NAME') ? DB_NAME : '';
$charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Erreur connexion base : ' . $e->getMessage());
}
