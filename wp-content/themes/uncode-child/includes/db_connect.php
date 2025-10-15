<?php
/**
 * Gestionnaire de connexion PDO pour l'application « Inventaire perso ».
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class InventoryDatabase
{
    /** @var InventoryDatabase|null */
    private static ?InventoryDatabase $instance = null;

    /** @var PDO */
    private PDO $pdo;

    /**
     * Constructeur privé pour appliquer le pattern Singleton.
     */
    private function __construct()
    {
        $this->pdo = $this->initialisePdo();
    }

    /**
     * Retourne l'unique instance de la classe.
     */
    public static function getInstance(): InventoryDatabase
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Fournit la connexion PDO prête à l'emploi.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prépare la liste des configurations à tester (WordPress d'abord, puis fallback).
     */
    private function credentialCandidates(): array
    {
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

        return $credentials;
    }

    /**
     * Initialise l'objet PDO en testant chaque configuration.
     */
    private function initialisePdo(): PDO
    {
        $charset = 'utf8mb4';
        $lastException = null;
        $seen = [];

        foreach ($this->credentialCandidates() as $candidate) {
            $key = sprintf('%s|%s|%s', $candidate['host'], $candidate['dbname'], $candidate['user']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            [$host, $port, $socket] = self::normaliseHost($candidate['host']);

            try {
                $dsn = sprintf('mysql:dbname=%s;charset=%s', $candidate['dbname'], $charset);
                if (null !== $socket) {
                    $dsn .= sprintf(';unix_socket=%s', $socket);
                } else {
                    $dsn .= sprintf(';host=%s', $host);
                    if (null !== $port) {
                        $dsn .= sprintf(';port=%d', $port);
                    }
                }

                $pdo = new PDO($dsn, $candidate['user'], $candidate['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
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
     * Normalise une valeur d'hôte (host:port ou socket).
     *
     * @return array{0:string,1:int|null,2:string|null}
     */
    private static function normaliseHost(string $host): array
    {
        $host = trim($host);
        $port = null;
        $socket = null;

        if ($host === '') {
            return ['localhost', null, null];
        }

        if (strpos($host, ':') !== false) {
            [$base, $suffix] = explode(':', $host, 2);
            if ($suffix !== '') {
                if ($suffix[0] === '/') {
                    $socket = $suffix;
                    $host = $base === '' ? 'localhost' : $base;
                } elseif (ctype_digit($suffix)) {
                    $port = (int) $suffix;
                    $host = $base;
                }
            }
        }

        return [$host, $port, $socket];
    }
}

/**
 * Fonction utilitaire pour rester compatible avec l'ancien code fonctionnel.
 */
function inventory_acquire_pdo(): PDO
{
    return InventoryDatabase::getInstance()->getConnection();
}
