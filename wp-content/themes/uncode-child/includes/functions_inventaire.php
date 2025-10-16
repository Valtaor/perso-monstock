<?php
/**
 * AJAX facade for the inventory dashboard.
 */

declare(strict_types=1);

// Attempt to bootstrap WordPress when the script is reached directly so we can
// leverage localisation helpers and authentication checks.
$rootPath = dirname(__FILE__, 5);
if (is_dir($rootPath) && file_exists($rootPath . '/wp-load.php')) {
    require_once $rootPath . '/wp-load.php';
}

require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Output a JSON payload and terminate.
 */
function inventory_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

/**
 * Build a translated string even when WordPress is unavailable.
 */
function inventory_translate(string $text): string
{
    if (function_exists('__')) {
        return __($text, 'uncode');
    }

    return $text;
}

if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
    inventory_json_response([
        'success' => false,
        'message' => inventory_translate('Authentification requise.'),
    ], 401);
}

global $pdo;

if (!$pdo instanceof PDO) {
    inventory_json_response([
        'success' => false,
        'message' => inventory_translate('Connexion à la base de données impossible.'),
    ], 500);
}

$inventorySupportsIncomplete = ensure_incomplete_flag($pdo);

$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

try {
    switch ($action) {
        case 'add_product':
            handle_add_product($pdo, $inventorySupportsIncomplete);
            break;
        case 'get_products':
            handle_get_products($pdo, $inventorySupportsIncomplete);
            break;
        case 'delete_product':
            handle_delete_product($pdo);
            break;
        case 'get_stats':
            handle_get_stats($pdo);
            break;
        case 'update_product':
            handle_update_product($pdo, $inventorySupportsIncomplete);
            break;
        default:
            inventory_json_response([
                'success' => false,
                'message' => inventory_translate('Action non reconnue.'),
            ], 400);
    }
} catch (PDOException $exception) {
    inventory_json_response([
        'success' => false,
        'message' => inventory_translate('Erreur base de données : ') . $exception->getMessage(),
    ], 500);
}

/**
 * Ensure the produits table exposes the a_completer flag.
 */
function ensure_incomplete_flag(PDO $pdo): bool
{
    try {
        $columnStmt = $pdo->query("SHOW COLUMNS FROM produits LIKE 'a_completer'");
        if ($columnStmt !== false && $columnStmt->fetch()) {
            return true;
        }

        $pdo->exec('ALTER TABLE produits ADD COLUMN a_completer TINYINT(1) NOT NULL DEFAULT 0');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

/**
 * Normalise uploaded file data and move it to the uploads directory.
 */
function inventory_handle_upload(): ?string
{
    if (empty($_FILES['image']['name'])) {
        return null;
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate("Erreur lors du téléchargement de l'image."),
        ], 400);
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }

    if ($mime === null || !in_array($mime, $allowedMimes, true)) {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate("Format d'image non supporté."),
        ], 415);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $imageName = uniqid('inv_', true) . '.' . $extension;
    $uploadDir = dirname(__FILE__) . '/../uploads/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate("Impossible de préparer le dossier d'upload."),
        ], 500);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate("Impossible de sauvegarder l'image."),
        ], 500);
    }

    return $imageName;
}

/**
 * Create a product.
 */
function handle_add_product(PDO $pdo, bool $supportsIncomplete): void
{
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $reference = trim((string) ($_POST['reference'] ?? ''));
    $prixAchat = isset($_POST['prix_achat']) ? (float) $_POST['prix_achat'] : 0.0;
    $prixVente = isset($_POST['prix_vente']) ? (float) $_POST['prix_vente'] : 0.0;
    $stock = isset($_POST['stock']) ? max(0, (int) $_POST['stock']) : 0;
    $description = trim((string) ($_POST['description'] ?? ''));
    $aCompleter = isset($_POST['a_completer']) && (int) $_POST['a_completer'] === 1 ? 1 : 0;

    if ($nom === '' || $reference === '') {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate('Merci de renseigner le nom et la référence.'),
        ], 422);
    }

    $imageName = inventory_handle_upload();

    $ajoutePar = 'Système';
    if (function_exists('wp_get_current_user')) {
        $currentUser = wp_get_current_user();
        if ($currentUser instanceof WP_User && $currentUser->exists()) {
            $ajoutePar = $currentUser->display_name ?: $currentUser->user_login;
        }
    }

    $query = 'INSERT INTO produits (nom, reference, prix_achat, prix_vente, stock, description, image, ajoute_par';
    $values = 'VALUES (:nom, :reference, :prix_achat, :prix_vente, :stock, :description, :image, :ajoute_par';
    $params = [
        ':nom' => $nom,
        ':reference' => $reference,
        ':prix_achat' => $prixAchat,
        ':prix_vente' => $prixVente,
        ':stock' => $stock,
        ':description' => $description,
        ':image' => $imageName,
        ':ajoute_par' => $ajoutePar,
    ];

    if ($supportsIncomplete) {
        $query .= ', a_completer';
        $values .= ', :a_completer';
        $params[':a_completer'] = $aCompleter;
    }

    $query .= ') ' . $values . ')';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    inventory_json_response([
        'success' => true,
        'message' => inventory_translate('Produit ajouté avec succès.'),
    ]);
}

/**
 * Fetch every product for the table view.
 */
function handle_get_products(PDO $pdo, bool $supportsIncomplete): void
{
    global $inventorySupportsIncomplete;

    $stmt = $pdo->query('SELECT * FROM produits ORDER BY id DESC');
    $products = [];

    while ($row = $stmt->fetch()) {
        $row['prix_achat'] = isset($row['prix_achat']) ? (float) $row['prix_achat'] : 0.0;
        $row['prix_vente'] = isset($row['prix_vente']) ? (float) $row['prix_vente'] : 0.0;
        $row['stock'] = isset($row['stock']) ? (int) $row['stock'] : 0;
        $row['a_completer'] = $supportsIncomplete && array_key_exists('a_completer', $row)
            ? (int) $row['a_completer']
            : 0;
        $products[] = $row;
    }

    inventory_json_response([
        'success' => true,
        'data' => $products,
    ]);
}

/**
 * Delete a single product.
 */
function handle_delete_product(PDO $pdo): void
{
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($id <= 0) {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate('Identifiant invalide.'),
        ], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM produits WHERE id = :id');
    $stmt->execute([':id' => $id]);

    inventory_json_response([
        'success' => true,
        'message' => inventory_translate('Produit supprimé.'),
    ]);
}

/**
 * Aggregate stats for the hero counters.
 */
function handle_get_stats(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT COALESCE(SUM(stock), 0) AS total_stock, COALESCE(SUM(prix_achat * stock), 0) AS total_achat, COALESCE(SUM(prix_vente * stock), 0) AS total_vente FROM produits');
    $result = $stmt->fetch() ?: [];

    $totalAchat = (float) ($result['total_achat'] ?? 0);
    $totalVente = (float) ($result['total_vente'] ?? 0);

    inventory_json_response([
        'success' => true,
        'data' => [
            'total_articles' => (int) ($result['total_stock'] ?? 0),
            'valeur_achat' => $totalAchat,
            'valeur_vente' => $totalVente,
            'marge_totale' => $totalVente - $totalAchat,
        ],
    ]);
}

/**
 * Update a single field on a product.
 */
function handle_update_product(PDO $pdo, bool $supportsIncomplete): void
{
    global $inventorySupportsIncomplete;

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $field = isset($_POST['field']) ? (string) $_POST['field'] : '';
    $value = $_POST['value'] ?? null;

    $allowedFields = [
        'prix_achat' => 'float',
        'prix_vente' => 'float',
        'stock' => 'int',
    ];

    if ($supportsIncomplete) {
        $allowedFields['a_completer'] = 'int';
    }

    if ($id <= 0 || !isset($allowedFields[$field])) {
        inventory_json_response([
            'success' => false,
            'message' => inventory_translate('Paramètres invalides.'),
        ], 422);
    }

    switch ($allowedFields[$field]) {
        case 'float':
            $value = max(0, (float) $value);
            break;
        case 'int':
            $value = max(0, (int) $value);
            if ($field === 'a_completer') {
                $value = $value === 1 ? 1 : 0;
            }
            break;
    }

    $stmt = $pdo->prepare("UPDATE produits SET {$field} = :value WHERE id = :id");
    $stmt->execute([
        ':value' => $value,
        ':id' => $id,
    ]);

    inventory_json_response([
        'success' => true,
        'message' => inventory_translate('Produit mis à jour.'),
    ]);
}
