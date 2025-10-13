<?php
/**
 * AJAX handlers for the inventory application.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/db_connect.php';

function inventory_debug_enabled(): bool
{
    return defined('WP_DEBUG') && WP_DEBUG;
}

/**
 * Ensure the current request is authorised.
 */
function inventory_verify_request(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error(
            [
                'message' => 'Authentification requise.',
            ],
            401
        );
    }

    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'inventory_nonce')) {
        wp_send_json_error(
            [
                'message' => 'Requête non autorisée.',
            ],
            403
        );
    }
}

/**
 * Obtain a PDO instance or send a JSON error response.
 */
function inventory_require_pdo(): PDO
{
    try {
        return inventory_acquire_pdo();
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Connexion à la base de données impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }
}

add_action('wp_ajax_inventory_add_product', 'inventory_handle_add_product');
add_action('wp_ajax_inventory_get_products', 'inventory_handle_get_products');
add_action('wp_ajax_inventory_delete_product', 'inventory_handle_delete_product');
add_action('wp_ajax_inventory_get_stats', 'inventory_handle_get_stats');
add_action('wp_ajax_inventory_update_product', 'inventory_handle_update_product');

/**
 * Handle product creation.
 */
function inventory_handle_add_product(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    $nom = isset($_POST['nom']) ? sanitize_text_field(wp_unslash((string) $_POST['nom'])) : '';
    $reference = isset($_POST['reference']) ? sanitize_text_field(wp_unslash((string) $_POST['reference'])) : '';
    $prixAchat = isset($_POST['prix_achat']) ? (float) wp_unslash((string) $_POST['prix_achat']) : 0.0;
    $prixVente = isset($_POST['prix_vente']) ? (float) wp_unslash((string) $_POST['prix_vente']) : 0.0;
    $stock = isset($_POST['stock']) ? (int) wp_unslash((string) $_POST['stock']) : 0;
    $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['description'])) : '';

    if ($nom === '' || $reference === '') {
        wp_send_json_error(
            [
                'message' => 'Merci de renseigner au minimum le nom et la référence.',
            ],
            422
        );
    }

    $imageName = null;
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(
                [
                    'message' => "Erreur lors du téléchargement de l'image.",
                ],
                400
            );
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']);
        } else {
            $mime = $file['type'] ?? '';
        }
        if (!in_array($mime, $allowedMimes, true)) {
            wp_send_json_error(
                [
                    'message' => "Format d'image non supporté.",
                ],
                415
            );
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $imageName = uniqid('inv_', true) . '.' . $extension;
        $uploadDir = trailingslashit(get_stylesheet_directory()) . 'uploads/';

        if (!file_exists($uploadDir)) {
            wp_mkdir_p($uploadDir);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
            wp_send_json_error(
                [
                    'message' => "Impossible de sauvegarder l'image.",
                ],
                500
            );
        }
    }

    $ajoutePar = 'Système';
    $currentUser = wp_get_current_user();
    if ($currentUser instanceof WP_User && $currentUser->exists()) {
        $ajoutePar = $currentUser->display_name ?: $currentUser->user_login;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO produits (nom, reference, prix_achat, prix_vente, stock, description, image, ajoute_par)
             VALUES (:nom, :reference, :prix_achat, :prix_vente, :stock, :description, :image, :ajoute_par)'
        );

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
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Enregistrement impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Produit ajouté avec succès.',
    ]);
}

/**
 * Return the list of products.
 */
function inventory_handle_get_products(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    try {
        $stmt = $pdo->query('SELECT * FROM produits ORDER BY id DESC');
        $products = [];

        while ($row = $stmt->fetch()) {
            $row['prix_achat'] = isset($row['prix_achat']) ? (float) $row['prix_achat'] : 0.0;
            $row['prix_vente'] = isset($row['prix_vente']) ? (float) $row['prix_vente'] : 0.0;
            $row['stock'] = isset($row['stock']) ? (int) $row['stock'] : 0;
            $products[] = $row;
        }
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Lecture des produits impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success($products);
}

/**
 * Delete a product by ID.
 */
function inventory_handle_delete_product(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error(
            [
                'message' => 'Identifiant invalide.',
            ],
            422
        );
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM produits WHERE id = :id');
        $stmt->execute([':id' => $id]);
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Suppression impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Produit supprimé.',
    ]);
}

/**
 * Send aggregated statistics.
 */
function inventory_handle_get_stats(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    try {
        $stmt = $pdo->query(
            'SELECT COALESCE(SUM(stock), 0) AS total_stock,
                    COALESCE(SUM(prix_achat * stock), 0) AS total_achat,
                    COALESCE(SUM(prix_vente * stock), 0) AS total_vente
             FROM produits'
        );
        $result = $stmt->fetch();
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Lecture des statistiques impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    $totalAchat = (float) ($result['total_achat'] ?? 0);
    $totalVente = (float) ($result['total_vente'] ?? 0);

    wp_send_json_success([
        'total_articles' => (int) ($result['total_stock'] ?? 0),
        'valeur_achat' => $totalAchat,
        'valeur_vente' => $totalVente,
        'marge_totale' => $totalVente - $totalAchat,
    ]);
}

/**
 * Update a specific product field.
 */
function inventory_handle_update_product(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    $field = isset($_POST['field']) ? sanitize_key((string) $_POST['field']) : '';
    $value = isset($_POST['value']) ? wp_unslash((string) $_POST['value']) : null;

    $allowedFields = [
        'prix_achat' => 'float',
        'prix_vente' => 'float',
        'stock' => 'int',
    ];

    if ($id <= 0 || !isset($allowedFields[$field])) {
        wp_send_json_error(
            [
                'message' => 'Paramètres invalides.',
            ],
            422
        );
    }

    switch ($allowedFields[$field]) {
        case 'int':
            $value = (int) $value;
            if ($value < 0) {
                $value = 0;
            }
            break;
        case 'float':
            $value = (float) str_replace(',', '.', (string) $value);
            if ($value < 0) {
                $value = 0.0;
            }
            $value = round($value, 2);
            break;
    }

    try {
        $stmt = $pdo->prepare("UPDATE produits SET {$field} = :value WHERE id = :id");
        $stmt->execute([
            ':value' => $value,
            ':id' => $id,
        ]);
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Mise à jour impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Produit mis à jour.',
    ]);
}
