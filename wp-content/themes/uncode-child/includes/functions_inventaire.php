<?php
/**
 * AJAX handlers for the inventory application.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/db_connect.php';

/*
 * -----------------------------------------------------------------------------
 * Schéma SQL requis pour le module catégories & tags
 * -----------------------------------------------------------------------------
 *
 * CREATE TABLE categories (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     nom VARCHAR(150) NOT NULL,
 *     couleur CHAR(7) DEFAULT '#c47b83',
 *     icone VARCHAR(50) DEFAULT NULL
 * );
 *
 * CREATE TABLE tags (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     nom VARCHAR(150) NOT NULL
 * );
 *
 * CREATE TABLE produits_categories (
 *     produit_id INT NOT NULL,
 *     categorie_id INT NOT NULL,
 *     PRIMARY KEY (produit_id, categorie_id),
 *     CONSTRAINT fk_pc_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
 *     CONSTRAINT fk_pc_categorie FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE CASCADE
 * );
 *
 * CREATE TABLE produits_tags (
 *     produit_id INT NOT NULL,
 *     tag_id INT NOT NULL,
 *     PRIMARY KEY (produit_id, tag_id),
 *     CONSTRAINT fk_pt_produit FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
 *     CONSTRAINT fk_pt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
 * );
 *
 * Indexes peuvent être ajoutés sur nom pour faciliter la recherche.
 *
 * -----------------------------------------------------------------------------
 * Instructions d'intégration
 * -----------------------------------------------------------------------------
 * 1. Exécuter le schéma ci-dessus (ou adapter aux préfixes personnalisés) sur la
 *    base MySQL existante afin de créer les nouvelles tables de taxonomie.
 * 2. Déployer les fichiers PHP/JS/CSS de l'application dans le thème enfant,
 *    puis vider le cache éventuel (plugins de cache/CDN).
 * 3. Vérifier que la page "Inventaire Bijoux" utilise bien ce template et que
 *    les utilisateurs autorisés disposent des capacités de gestion.
 * 4. Depuis l'interface, créer vos catégories (couleur + icône) et tags ; ils
 *    seront ensuite disponibles dans le formulaire produit, le module de
 *    filtrage et l'éditeur rapide d'associations.
 * -----------------------------------------------------------------------------
 */

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

/**
 * Sanitize a liste d'identifiants envoyée depuis le front.
 */
function inventory_sanitize_id_list($raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }
    }

    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

/**
 * Synchronise les relations catégories/tags pour un produit.
 */
function inventory_sync_product_terms(PDO $pdo, int $productId, array $categoryIds, array $tagIds): void
{
    try {
        $pdo->prepare('DELETE FROM produits_categories WHERE produit_id = :id')->execute([':id' => $productId]);
    } catch (PDOException $exception) {
        throw $exception;
    }

    if (!empty($categoryIds)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO produits_categories (produit_id, categorie_id) VALUES (?, ?)');
            foreach ($categoryIds as $categoryId) {
                $stmt->execute([$productId, $categoryId]);
            }
        } catch (PDOException $exception) {
            throw $exception;
        }
    }

    try {
        $pdo->prepare('DELETE FROM produits_tags WHERE produit_id = :id')->execute([':id' => $productId]);
    } catch (PDOException $exception) {
        throw $exception;
    }

    if (!empty($tagIds)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO produits_tags (produit_id, tag_id) VALUES (?, ?)');
            foreach ($tagIds as $tagId) {
                $stmt->execute([$productId, $tagId]);
            }
        } catch (PDOException $exception) {
            throw $exception;
        }
    }
}

/**
 * Rattache les catégories et tags à une liste de produits.
 */
function inventory_enrich_products_with_terms(PDO $pdo, array &$products): void
{
    if (empty($products)) {
        return;
    }

    $productIds = array_map(static function ($item) {
        return (int) ($item['id'] ?? 0);
    }, $products);
    $productIds = array_filter($productIds);
    if (empty($productIds)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $productMap = [];
    foreach ($products as $index => $product) {
        $products[$index]['categories'] = [];
        $products[$index]['tags'] = [];
        $productMap[(int) $product['id']] = &$products[$index];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT pc.produit_id, c.id, c.nom, c.couleur, c.icone
             FROM produits_categories pc
             INNER JOIN categories c ON c.id = pc.categorie_id
             WHERE pc.produit_id IN ({$placeholders})
             ORDER BY c.nom ASC"
        );
        $stmt->execute($productIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = (int) $row['produit_id'];
            if (isset($productMap[$productId])) {
                $productMap[$productId]['categories'][] = [
                    'id'      => (int) $row['id'],
                    'nom'     => $row['nom'],
                    'couleur' => $row['couleur'],
                    'icone'   => $row['icone'],
                ];
            }
        }
    } catch (PDOException $exception) {
        throw $exception;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT pt.produit_id, t.id, t.nom
             FROM produits_tags pt
             INNER JOIN tags t ON t.id = pt.tag_id
             WHERE pt.produit_id IN ({$placeholders})
             ORDER BY t.nom ASC"
        );
        $stmt->execute($productIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $productId = (int) $row['produit_id'];
            if (isset($productMap[$productId])) {
                $productMap[$productId]['tags'][] = [
                    'id'  => (int) $row['id'],
                    'nom' => $row['nom'],
                ];
            }
        }
    } catch (PDOException $exception) {
        throw $exception;
    }
}

add_action('wp_ajax_inventory_add_product', 'inventory_handle_add_product');
add_action('wp_ajax_inventory_get_products', 'inventory_handle_get_products');
add_action('wp_ajax_inventory_delete_product', 'inventory_handle_delete_product');
add_action('wp_ajax_inventory_get_stats', 'inventory_handle_get_stats');
add_action('wp_ajax_inventory_update_product', 'inventory_handle_update_product');
add_action('wp_ajax_inventory_get_taxonomies', 'inventory_handle_get_taxonomies');
add_action('wp_ajax_inventory_save_category', 'inventory_handle_save_category');
add_action('wp_ajax_inventory_delete_category', 'inventory_handle_delete_category');
add_action('wp_ajax_inventory_save_tag', 'inventory_handle_save_tag');
add_action('wp_ajax_inventory_delete_tag', 'inventory_handle_delete_tag');
add_action('wp_ajax_inventory_assign_terms', 'inventory_handle_assign_terms');

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
    $categoryIds = inventory_sanitize_id_list($_POST['categories'] ?? []);
    $tagIds = inventory_sanitize_id_list($_POST['tags'] ?? []);

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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO produits (nom, reference, prix_achat, prix_vente, stock, description, image, ajoute_par)'
            . ' VALUES (:nom, :reference, :prix_achat, :prix_vente, :stock, :description, :image, :ajoute_par)'
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

        $productId = (int) $pdo->lastInsertId();
        inventory_sync_product_terms($pdo, $productId, $categoryIds, $tagIds);

        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

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

    $categoryFilter = inventory_sanitize_id_list($_POST['categories'] ?? []);
    $tagFilter = inventory_sanitize_id_list($_POST['tags'] ?? []);

    try {
        $sql = 'SELECT DISTINCT p.* FROM produits p';
        $joins = [];
        $conditions = [];
        $params = [];

        if (!empty($categoryFilter)) {
            $placeholders = implode(',', array_fill(0, count($categoryFilter), '?'));
            $joins[] = 'INNER JOIN produits_categories pc_filter ON pc_filter.produit_id = p.id';
            $conditions[] = "pc_filter.categorie_id IN ({$placeholders})";
            $params = array_merge($params, $categoryFilter);
        }

        if (!empty($tagFilter)) {
            $placeholders = implode(',', array_fill(0, count($tagFilter), '?'));
            $joins[] = 'INNER JOIN produits_tags pt_filter ON pt_filter.produit_id = p.id';
            $conditions[] = "pt_filter.tag_id IN ({$placeholders})";
            $params = array_merge($params, $tagFilter);
        }

        if (!empty($joins)) {
            $sql .= ' ' . implode(' ', $joins);
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.id DESC';

        if (!empty($params)) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->query($sql);
        }

        $products = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['prix_achat'] = isset($row['prix_achat']) ? (float) $row['prix_achat'] : 0.0;
            $row['prix_vente'] = isset($row['prix_vente']) ? (float) $row['prix_vente'] : 0.0;
            $row['stock'] = isset($row['stock']) ? (int) $row['stock'] : 0;
            $products[] = $row;
        }

        inventory_enrich_products_with_terms($pdo, $products);
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
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM produits_categories WHERE produit_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = $pdo->prepare('DELETE FROM produits_tags WHERE produit_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = $pdo->prepare('DELETE FROM produits WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

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

/**
 * Récupère catégories et tags.
 */
function inventory_handle_get_taxonomies(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    try {
        $categoriesStmt = $pdo->query('SELECT id, nom, couleur, icone FROM categories ORDER BY nom ASC');
        $categories = $categoriesStmt ? $categoriesStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Impossible de charger les catégories.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    try {
        $tagsStmt = $pdo->query('SELECT id, nom FROM tags ORDER BY nom ASC');
        $tags = $tagsStmt ? $tagsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Impossible de charger les tags.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'categories' => array_map(static function ($row) {
            return [
                'id'      => (int) $row['id'],
                'nom'     => $row['nom'],
                'couleur' => $row['couleur'],
                'icone'   => $row['icone'],
            ];
        }, $categories ?? []),
        'tags' => array_map(static function ($row) {
            return [
                'id'  => (int) $row['id'],
                'nom' => $row['nom'],
            ];
        }, $tags ?? []),
    ]);
}

/**
 * Création ou mise à jour d'une catégorie.
 */
function inventory_handle_save_category(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    $nom = isset($_POST['nom']) ? sanitize_text_field(wp_unslash((string) $_POST['nom'])) : '';
    $couleur = isset($_POST['couleur']) ? sanitize_hex_color((string) $_POST['couleur']) : '';
    $icone = isset($_POST['icone']) ? sanitize_text_field(wp_unslash((string) $_POST['icone'])) : '';

    if ($nom === '') {
        wp_send_json_error(
            [
                'message' => 'Le nom de la catégorie est obligatoire.',
            ],
            422
        );
    }

    if ($couleur === '') {
        $couleur = '#c47b83';
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE categories SET nom = :nom, couleur = :couleur, icone = :icone WHERE id = :id');
            $stmt->execute([
                ':nom' => $nom,
                ':couleur' => $couleur,
                ':icone' => $icone,
                ':id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (nom, couleur, icone) VALUES (:nom, :couleur, :icone)');
            $stmt->execute([
                ':nom' => $nom,
                ':couleur' => $couleur,
                ':icone' => $icone,
            ]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Impossible d\'enregistrer la catégorie.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Catégorie enregistrée.',
        'category' => [
            'id' => $id,
            'nom' => $nom,
            'couleur' => $couleur,
            'icone' => $icone,
        ],
    ]);
}

/**
 * Suppression d'une catégorie.
 */
function inventory_handle_delete_category(): void
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
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM produits_categories WHERE categorie_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        wp_send_json_error(
            [
                'message' => 'Suppression de la catégorie impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Catégorie supprimée.',
    ]);
}

/**
 * Création ou mise à jour d'un tag.
 */
function inventory_handle_save_tag(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    $nom = isset($_POST['nom']) ? sanitize_text_field(wp_unslash((string) $_POST['nom'])) : '';

    if ($nom === '') {
        wp_send_json_error(
            [
                'message' => 'Le nom du tag est obligatoire.',
            ],
            422
        );
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE tags SET nom = :nom WHERE id = :id');
            $stmt->execute([
                ':nom' => $nom,
                ':id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO tags (nom) VALUES (:nom)');
            $stmt->execute([
                ':nom' => $nom,
            ]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $exception) {
        wp_send_json_error(
            [
                'message' => 'Impossible d\'enregistrer le tag.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Tag enregistré.',
        'tag' => [
            'id' => $id,
            'nom' => $nom,
        ],
    ]);
}

/**
 * Suppression d'un tag.
 */
function inventory_handle_delete_tag(): void
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
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM produits_tags WHERE tag_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = $pdo->prepare('DELETE FROM tags WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        wp_send_json_error(
            [
                'message' => 'Suppression du tag impossible.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Tag supprimé.',
    ]);
}

/**
 * Affecte catégories et tags à un produit.
 */
function inventory_handle_assign_terms(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();

    $productId = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    $categoryIds = inventory_sanitize_id_list($_POST['categories'] ?? []);
    $tagIds = inventory_sanitize_id_list($_POST['tags'] ?? []);

    if ($productId <= 0) {
        wp_send_json_error(
            [
                'message' => 'Identifiant produit invalide.',
            ],
            422
        );
    }

    try {
        $pdo->beginTransaction();
        inventory_sync_product_terms($pdo, $productId, $categoryIds, $tagIds);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        wp_send_json_error(
            [
                'message' => 'Impossible de mettre à jour les associations.',
                'details' => inventory_debug_enabled() ? $exception->getMessage() : null,
            ],
            500
        );
    }

    $products = [['id' => $productId]];
    inventory_enrich_products_with_terms($pdo, $products);
    $product = $products[0] ?? ['categories' => [], 'tags' => []];

    wp_send_json_success([
        'message' => 'Associations mises à jour.',
        'categories' => $product['categories'] ?? [],
        'tags' => $product['tags'] ?? [],
    ]);
}
