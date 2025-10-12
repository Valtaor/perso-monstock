<?php
/**
 * Database connection for the inventory application.
 */
$host = "db50000543572.hosting-data.io";
$user = "dbo50000543572";
$pass = "14Juillet@";
$dbname = "dbs521868";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Erreur connexion base : " . $e->getMessage());
}
