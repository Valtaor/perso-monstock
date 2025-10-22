<?php
/**
 * Fonctions AJAX Inventaire – BellaFrance
 * Compatible avec le script-inventaire.js
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

/**
 * Connexion PDO
 */
function inventory_db_get_pdo($forceReconnect = false)
{
    static $pdo = null;
    if ($pdo instanceof PDO && !$forceReconnect) {
        return $pdo;
    }

    try {
        global $wpdb;
        $dsn = "mysql:host={$wpdb->dbhost};dbname={$wpdb->dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $wpdb->dbuser, $wpdb->dbpassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('Inventory - Connexion PDO échouée : ' . $e->getMessage());
        $pdo = null;
    }

    return $pdo;
}

/**
 * Vérification de la table inventaire
 */
function inventory_db_ensure_table()
{
    global $wpdb;
    $table = $wpdb->prefix . 'inventaire';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL,
        reference VARCHAR(255) DEFAULT NULL,
        emplacement VARCHAR(255) DEFAULT NULL,
        prix_achat DECIMAL(10,2) DEFAULT 0,
        prix_vente DECIMAL(10,2) DEFAULT 0,
        stock INT DEFAULT 0,
        a_completer TINYINT(1) DEFAULT 0,
        notes TEXT,
        description TEXT,
        date_achat DATE DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        date_created DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Récupération de tous les produits
 */
function inventory_get_products()
{
    $pdo = inventory_db_get_pdo();
    if (!$pdo) {
        wp_send_json_error(['message' => 'Connexion à la base impossible']);
    }

    $stmt = $pdo->query("SELECT * FROM {$GLOBALS['wpdb']->prefix}inventaire ORDER BY id DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    wp_send_json_success($data);
}
add_action('wp_ajax_get_products', 'inventory_get_products');
add_action('wp_ajax_nopriv_get_products', 'inventory_get_products');

/**
 * Ajout d’un produit
 */
function inventory_add_product()
{
    $pdo = inventory_db_get_pdo();
    if (!$pdo) {
        wp_send_json_error(['message' => 'Connexion à la base impossible']);
    }

    $fields = [
        'nom'          => sanitize_text_field($_POST['nom'] ?? ''),
        'reference'    => sanitize_text_field($_POST['reference'] ?? ''),
        'emplacement'  => sanitize_text_field($_POST['emplacement'] ?? ''),
        'prix_achat'   => floatval($_POST['prix_achat'] ?? 0),
        'prix_vente'   => floatval($_POST['prix_vente'] ?? 0),
        'stock'        => intval($_POST['stock'] ?? 0),
        'a_completer'  => isset($_POST['a_completer']) ? 1 : 0,
        'notes'        => sanitize_textarea_field($_POST['notes'] ?? ''),
        'description'  => sanitize_textarea_field($_POST['description'] ?? ''),
        'date_achat'   => sanitize_text_field($_POST['date_achat'] ?? ''),
        'image'        => '',
    ];

    // Gestion image
    if (!empty($_FILES['product-image']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload($_FILES['product-image'], ['test_form' => false]);
        if (!isset($upload['error'])) {
            $fields['image'] = basename($upload['file']);
        } else {
            wp_send_json_error(['message' => 'Erreur upload image : ' . $upload['error']]);
        }
    }

    try {
        $sql = "INSERT INTO {$GLOBALS['wpdb']->prefix}inventaire 
                (nom, reference, emplacement, prix_achat, prix_vente, stock, a_completer, notes, description, date_achat, image)
                VALUES (:nom, :reference, :emplacement, :prix_achat, :prix_vente, :stock, :a_completer, :notes, :description, :date_achat, :image)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($fields);
        wp_send_json_success(['id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur base de données : ' . $e->getMessage()]);
    }
}
add_action('wp_ajax_add_product', 'inventory_add_product');
add_action('wp_ajax_nopriv_add_product', 'inventory_add_product');

/**
 * Mise à jour d’un champ produit
 */
function inventory_update_product()
{
    $id = intval($_POST['id'] ?? 0);
    $field = sanitize_key($_POST['field'] ?? '');
    $value = $_POST['value'] ?? '';

    if ($id <= 0 || !$field) {
        wp_send_json_error(['message' => 'Paramètres invalides.']);
    }

    $pdo = inventory_db_get_pdo();
    if (!$pdo) {
        wp_send_json_error(['message' => 'Connexion à la base impossible']);
    }

    $allowed = ['nom', 'reference', 'emplacement', 'prix_achat', 'prix_vente', 'stock', 'a_completer', 'notes', 'description', 'date_achat'];
    if (!in_array($field, $allowed, true)) {
        wp_send_json_error(['message' => 'Champ non autorisé.']);
    }

    try {
        $sql = "UPDATE {$GLOBALS['wpdb']->prefix}inventaire SET {$field} = :value WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':value' => $value, ':id' => $id]);
        wp_send_json_success(['field' => $field, 'value' => $value]);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur base de données : ' . $e->getMessage()]);
    }
}
add_action('wp_ajax_update_product', 'inventory_update_product');

/**
 * Suppression d’un produit
 */
function inventory_delete_product()
{
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID invalide.']);
    }

    $pdo = inventory_db_get_pdo();
    if (!$pdo) {
        wp_send_json_error(['message' => 'Connexion à la base impossible']);
    }

    try {
        $sql = "DELETE FROM {$GLOBALS['wpdb']->prefix}inventaire WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        wp_send_json_success(['deleted' => $id]);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur base de données : ' . $e->getMessage()]);
    }
}
add_action('wp_ajax_delete_product', 'inventory_delete_product');

/**
 * Initialisation à l’activation
 */
function inventory_activate()
{
    inventory_db_ensure_table();
}
register_activation_hook(__FILE__, 'inventory_activate');
