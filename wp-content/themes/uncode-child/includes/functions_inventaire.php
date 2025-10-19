<?php
/**
 * Logique AJAX pour l'inventaire - Version finale corrigée et complète
 * Inclut la gestion des champs 'notes' et 'date_achat'.
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Sécurité
}

require_once __DIR__ . '/db_connect.php';

/**
 * Retourne la liste des colonnes disponibles dans la table produits.
 *
 * @return array<string, bool>
 */
function inventory_get_table_columns(PDO $pdo): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM produits');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (!empty($column['Field'])) {
                $cache[$column['Field']] = true;
            }
        }
    } catch (PDOException $e) {
        error_log('Inventory - Impossible de récupérer les colonnes de la table produits : ' . $e->getMessage());
        $cache = [];
    }

    return $cache;
}

// --- Enregistrement des actions AJAX WordPress ---
add_action('wp_ajax_get_products', 'inventory_get_products_ajax');
add_action('wp_ajax_add_product', 'inventory_add_product_ajax');
add_action('wp_ajax_update_product', 'inventory_update_product_ajax');
add_action('wp_ajax_delete_product', 'inventory_delete_product_ajax');

/**
 * Vérification préliminaire (Login + DB) pour chaque appel AJAX
 */
function inventory_ajax_precheck(): PDO
{
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Authentification requise.'], 401);
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        // Tentative de reconnexion si $pdo est null
        require_once __DIR__ . '/db_connect.php';
        if (!$pdo instanceof PDO) {
            wp_send_json_error(['message' => 'Connexion DB impossible.'], 500);
        }
    }

    return $pdo;
}

/**
 * Récupère tous les produits pour affichage
 */
function inventory_get_products_ajax(): void
{
    $pdo = inventory_ajax_precheck();
    try {
        $stmt = $pdo->query('SELECT * FROM produits ORDER BY id DESC');
        // Assurer les bons types de données pour JavaScript
        $products = array_map(static function (array $p): array {
            $p['id'] = intval($p['id']);
            $p['prix_achat'] = floatval($p['prix_achat'] ?? 0);
            $p['prix_vente'] = floatval($p['prix_vente'] ?? 0);
            $p['stock'] = intval($p['stock'] ?? 0);
            $p['a_completer'] = intval($p['a_completer'] ?? 0);
            $p['emplacement'] = strval($p['emplacement'] ?? '');
            $p['notes'] = strval($p['notes'] ?? '');
            $p['date_achat'] = $p['date_achat'] ?? null; // Garder null si absent
            $p['image'] = $p['image'] ?? null; // S'assurer que l'image est présente ou null
            return $p;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        wp_send_json_success($products);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur DB (get): ' . $e->getMessage()], 500);
    }
}

/**
 * Gère l'upload d'image via WordPress de manière sécurisée
 */
function inventory_handle_wp_upload(): ?string
{
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        return null;
    }

    if (empty($_FILES['image']['name']) || (int) $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null; // Pas d'image fournie ou erreur initiale
    }

    $file = $_FILES['image'];

    // Vérification type MIME avec WordPress
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_info = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if (empty($file_info['ext']) || empty($file_info['type']) || !in_array($file_info['type'], $allowed_types, true)) {
        throw new Exception('Type de fichier non autorisé. Images acceptées : JPG, PNG, GIF, WebP.');
    }

    // Utiliser wp_handle_upload
    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    // Définir le répertoire cible dans 'uploads/inventory'
    add_filter('upload_dir', 'inventory_set_upload_dir');
    $upload_overrides = ['test_form' => false];
    $movefile = wp_handle_upload($file, $upload_overrides);
    remove_filter('upload_dir', 'inventory_set_upload_dir'); // Nettoyer le filtre après usage

    if (isset($movefile['error'])) {
        throw new Exception('Erreur Upload WordPress: ' . $movefile['error']);
    }

    // Retourner seulement le nom du fichier (il sera dans uploads/inventory/)
    return basename($movefile['file']);
}

/**
 * Modifie temporairement le répertoire d'upload pour WordPress
 */
function inventory_set_upload_dir(array $dirs): array
{
    $subdir = '/inventory';
    $dirs['subdir'] = $subdir;
    $dirs['path'] = $dirs['basedir'] . $subdir;
    $dirs['url'] = $dirs['baseurl'] . $subdir;
    return $dirs;
}

/**
 * Ajoute un produit (avec tous les champs de la DB)
 */
function inventory_add_product_ajax(): void
{
    $pdo = inventory_ajax_precheck();

    // Validation des champs requis
    $nom = isset($_POST['nom']) ? trim((string) $_POST['nom']) : '';
    $reference = isset($_POST['reference']) ? trim((string) $_POST['reference']) : '';
    if ($nom === '' || $reference === '') {
        wp_send_json_error(['message' => 'Le nom et la référence sont requis.'], 400);
    }

    // Récupération de tous les champs
    $prix_achat = isset($_POST['prix_achat']) ? floatval($_POST['prix_achat']) : 0;
    $prix_vente = isset($_POST['prix_vente']) ? floatval($_POST['prix_vente']) : 0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $a_completer = (!empty($_POST['a_completer']) && (string) $_POST['a_completer'] === '1') ? 1 : 0;
    $emplacement = isset($_POST['emplacement']) ? trim((string) $_POST['emplacement']) : null;
    $notes = isset($_POST['notes']) ? trim((string) $_POST['notes']) : null;
    $date_achat_input = isset($_POST['date_achat']) ? trim((string) $_POST['date_achat']) : null;

    // Validation/Formatage de la date
    $date_achat = null;
    if ($date_achat_input && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_achat_input)) {
        $d = DateTime::createFromFormat('Y-m-d', $date_achat_input);
        if ($d instanceof DateTime && $d->format('Y-m-d') === $date_achat_input) {
            $date_achat = $date_achat_input;
        }
    }

    // Gestion de l'upload
    $image_filename = null;
    try {
        $image_filename = inventory_handle_wp_upload();
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Erreur Upload: ' . $e->getMessage()], 500);
    }

    $current_user = wp_get_current_user();
    $ajoute_par = $current_user->display_name ?: $current_user->user_login;

    $columns = inventory_get_table_columns($pdo);

    $columnMap = [
        'nom' => $nom,
        'reference' => $reference,
        'emplacement' => $emplacement,
        'prix_achat' => $prix_achat,
        'prix_vente' => $prix_vente,
        'stock' => $stock,
        'description' => $description,
        'notes' => $notes,
        'date_achat' => $date_achat,
        'a_completer' => $a_completer,
        'ajoute_par' => $ajoute_par,
        'image' => $image_filename,
    ];

    $insertColumns = [];
    $placeholders = [];
    $params = [];

    foreach ($columnMap as $columnName => $value) {
        if (!array_key_exists($columnName, $columns)) {
            continue;
        }

        $insertColumns[] = '`' . $columnName . '`';
        $placeholder = ':' . $columnName;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $value;
    }

    if (!in_array('`nom`', $insertColumns, true) || !in_array('`reference`', $insertColumns, true)) {
        wp_send_json_error(['message' => 'Colonnes obligatoires manquantes dans la table produits.'], 500);
    }

    $sql = sprintf(
        'INSERT INTO produits (%s) VALUES (%s)',
        implode(', ', $insertColumns),
        implode(', ', $placeholders)
    );

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        wp_send_json_success(['message' => 'Produit ajouté avec succès.', 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur DB (add): ' . $e->getMessage()], 500);
    }
}

/**
 * Met à jour un champ spécifique d'un produit
 */
function inventory_update_product_ajax(): void
{
    $pdo = inventory_ajax_precheck();

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $field = isset($_POST['field']) ? (string) $_POST['field'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';

    // Champs autorisés à la modification directe
    $columns = inventory_get_table_columns($pdo);
    $allowed_fields = ['prix_achat', 'prix_vente', 'stock', 'a_completer', 'emplacement', 'notes', 'date_achat', 'description'];
    $allowed_fields = array_values(array_filter($allowed_fields, static function (string $column) use ($columns): bool {
        return array_key_exists($column, $columns);
    }));

    if ($id <= 0 || !in_array($field, $allowed_fields, true)) {
        wp_send_json_error(['message' => 'Données de mise à jour invalides (champ non autorisé ou ID manquant).'], 400);
    }

    // Nettoyage/Validation de la valeur en fonction du champ
    if ($field === 'prix_achat' || $field === 'prix_vente') {
        $value = preg_replace('/[^\d,\.]/', '', str_replace(',', '.', (string) $value));
        $value = floatval($value);
    } elseif ($field === 'stock') {
        $value = intval(preg_replace('/[^\d]/', '', (string) $value));
        $value = max(0, $value);
    } elseif ($field === 'a_completer') {
        $value = (($value === '1') || ($value === 1) || ($value === true) || ($value === 'true')) ? 1 : 0;
    } elseif ($field === 'date_achat') {
        $value = trim((string) $value);
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $d = DateTime::createFromFormat('Y-m-d', $value);
            if (!$d instanceof DateTime || $d->format('Y-m-d') !== $value) {
                $value = null;
            }
        } elseif ($value === '') {
            $value = null; // Permettre de vider la date
        } else {
            wp_send_json_error(['message' => 'Format de date invalide (AAAA-MM-JJ requis).'], 400);
        }
    } else {
        // Pour les champs texte comme 'emplacement', 'notes', 'description'
        $value = trim(wp_kses_post((string) $value));
        if ($value === '') {
            $value = null;
        }
    }

    // Utiliser des backticks pour protéger le nom de colonne
    $sql = 'UPDATE produits SET `' . $field . '` = :value WHERE id = :id';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':value' => $value, ':id' => $id]);
        wp_send_json_success(['message' => 'Produit mis à jour.', 'newValue' => $value]);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur DB (update): ' . $e->getMessage()], 500);
    }
}

/**
 * Supprime un produit et son image associée
 */
function inventory_delete_product_ajax(): void
{
    $pdo = inventory_ajax_precheck();
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        wp_send_json_error(['message' => 'ID invalide.'], 400);
    }

    // 1. Récupérer le nom de l'image avant de supprimer l'entrée de la DB si la colonne existe
    $columns = inventory_get_table_columns($pdo);
    $image_to_delete = null;
    if (array_key_exists('image', $columns)) {
        try {
            $stmt = $pdo->prepare('SELECT image FROM produits WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $image_to_delete = $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            error_log('Inventory - Erreur récupération image avant suppression (ID: ' . $id . '): ' . $e->getMessage());
        }
    }

    // 2. Supprimer l'entrée de la base de données
    $sql = 'DELETE FROM produits WHERE id = :id';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        // 3. Si la suppression DB a réussi ET qu'on a un nom d'image, supprimer le fichier
        if ($image_to_delete) {
            $upload_dir_info = wp_upload_dir();
            // Le chemin de base est wp-content/uploads/inventory/
            $file_path = $upload_dir_info['basedir'] . '/inventory/' . $image_to_delete;
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    error_log('Inventory - Impossible de supprimer le fichier image: ' . $file_path);
                } else {
                    error_log('Inventory - Fichier image supprimé: ' . $file_path);
                }
            } else {
                error_log('Inventory - Fichier image non trouvé pour suppression: ' . $file_path);
            }
        }

        wp_send_json_success(['message' => 'Produit supprimé.']);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur DB (delete): ' . $e->getMessage()], 500);
    }
}
