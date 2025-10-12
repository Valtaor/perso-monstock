<?php
/**
 * AJAX handler for the inventory application.
 */

declare(strict_types=1);

// Bootstrap WordPress to leverage authentication helpers when available.
$rootPath = dirname(__FILE__, 5);
if (is_dir($rootPath) && file_exists($rootPath . '/wp-load.php')) {
    require_once $rootPath . '/wp-load.php';
}

require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Output a JSON response and terminate.
 */
function inventory_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
    inventory_json_response([
        'success' => false,
        'message' => __('Authentification requise.', 'uncode'),
    ], 401);
}

global $pdo;

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'add_product':
        handle_add_product($pdo);
        break;
    case 'get_products':
        handle_get_products($pdo);
        break;
    case 'delete_product':
        handle_delete_product($pdo);
        break;
    case 'get_stats':
        handle_get_stats($pdo);
        break;
    case 'update_product':
        handle_update_product($pdo);
        break;
    default:
        inventory_json_response([
            'success' => false,
            'message' => __('Action non reconnue.', 'uncode'),
        ], 400);
}

/**
 * Handle the creation of a new product.
 */
function handle_add_product(PDO $pdo): void
{
    $nom = trim($_POST['nom'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $prixAchat = isset($_POST['prix_achat']) ? (float) $_POST['prix_achat'] : 0.0;
    $prixVente = isset($_POST['prix_vente']) ? (float) $_POST['prix_vente'] : 0.0;
    $stock = isset($_POST['stock']) ? (int) $_POST['stock'] : 0;
    $description = trim($_POST['description'] ?? '');

    if ($nom === '' || $reference === '') {
        inventory_json_response([
            'success' => false,
            'message' => __('Merci de renseigner au minimum le nom et la référence.', 'uncode'),
        ], 422);
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $imageName = null;

    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            inventory_json_response([
                'success' => false,
                'message' => __('Erreur lors du téléchargement de l\'image.', 'uncode'),
            ], 400);
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        } else {
            $mime = mime_content_type($file['tmp_name']);
        }
        if (!in_array($mime, $allowedMimes, true)) {
            inventory_json_response([
                'success' => false,
                'message' => __('Format d\'image non supporté.', 'uncode'),
            ], 415);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $imageName = uniqid('inv_', true) . '.' . $extension;
        $uploadDir = dirname(__FILE__, 1) . '/../uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
            inventory_json_response([
                'success' => false,
                'message' => __('Impossible de sauvegarder l\'image.', 'uncode'),
            ], 500);
        }
    }

    $ajoutePar = 'Système';
    if (function_exists('wp_get_current_user')) {
        $currentUser = wp_get_current_user();
        if ($currentUser instanceof WP_User && $currentUser->exists()) {
            $ajoutePar = $currentUser->display_name ?: $currentUser->user_login;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO produits (nom, reference, prix_achat, prix_vente, stock, description, image, ajoute_par) VALUES (:nom, :reference, :prix_achat, :prix_vente, :stock, :description, :image, :ajoute_par)');

    $stmt->execute([
        ':nom' => $nom,
        ':reference' => $reference,
        ':prix_achat' => $prixAchat,
        ':prix_vente' => $prixVente,
        ':stock' => $stock,
        ':description' => $description,
        ':image' => $imageName,
        ':ajoute_par' => $ajoutePar,
    ]);

    inventory_json_response([
        'success' => true,
        'message' => __('Produit ajouté avec succès.', 'uncode'),
    ]);
}

/**
 * Retrieve products and send them back to the front-end.
 */
function handle_get_products(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT * FROM produits ORDER BY id DESC');
    $products = [];

    while ($row = $stmt->fetch()) {
        $row['prix_achat'] = isset($row['prix_achat']) ? (float) $row['prix_achat'] : 0.0;
        $row['prix_vente'] = isset($row['prix_vente']) ? (float) $row['prix_vente'] : 0.0;
        $row['stock'] = isset($row['stock']) ? (int) $row['stock'] : 0;
        $products[] = $row;
    }

    inventory_json_response([
        'success' => true,
        'data' => $products,
    ]);
}

/**
 * Remove a product from the inventory.
 */
function handle_delete_product(PDO $pdo): void
{
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($id <= 0) {
        inventory_json_response([
            'success' => false,
            'message' => __('Identifiant invalide.', 'uncode'),
        ], 422);
    }

    $stmt = $pdo->prepare('DELETE FROM produits WHERE id = :id');
    $stmt->execute([':id' => $id]);

    inventory_json_response([
        'success' => true,
        'message' => __('Produit supprimé.', 'uncode'),
    ]);
}

/**
 * Return aggregated statistics about the inventory.
 */
function handle_get_stats(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT COALESCE(SUM(stock), 0) AS total_stock, COALESCE(SUM(prix_achat * stock), 0) AS total_achat, COALESCE(SUM(prix_vente * stock), 0) AS total_vente FROM produits');
    $result = $stmt->fetch();

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
 * Update a single field for a specific product.
 */
function handle_update_product(PDO $pdo): void
{
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? null;

    $allowedFields = [
        'prix_achat' => 'float',
        'prix_vente' => 'float',
        'stock' => 'int',
    ];

    if ($id <= 0 || !isset($allowedFields[$field])) {
        inventory_json_response([
            'success' => false,
            'message' => __('Paramètres invalides.', 'uncode'),
        ], 422);
    }

    switch ($allowedFields[$field]) {
        case 'float':
            $value = (float) $value;
            break;
        case 'int':
            $value = (int) $value;
            break;
    }

    $stmt = $pdo->prepare("UPDATE produits SET {$field} = :value WHERE id = :id");
    $stmt->execute([
        ':value' => $value,
        ':id' => $id,
    ]);

    inventory_json_response([
        'success' => true,
        'message' => __('Produit mis à jour.', 'uncode'),
    ]);
}
