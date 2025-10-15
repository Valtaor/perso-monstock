<?php
/**
 * Gestion centralisée des opérations AJAX de l'application "Inventaire perso".
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/db_connect.php';

/**
 * Exception dédiée aux erreurs de validation côté serveur.
 */
class InventoryValidationException extends RuntimeException
{
}

/**
 * Ensemble d'outils de validation et de nettoyage des données entrantes.
 */
final class InventoryRequestValidator
{
    public static function text(array $source, string $key, ?int $maxLength = null): string
    {
        if (!isset($source[$key])) {
            return '';
        }

        $value = sanitize_text_field(wp_unslash((string) $source[$key]));
        if (null !== $maxLength && $maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    public static function textarea(array $source, string $key): string
    {
        if (!isset($source[$key])) {
            return '';
        }

        return sanitize_textarea_field(wp_unslash((string) $source[$key]));
    }

    public static function decimal(array $source, string $key): float
    {
        if (!isset($source[$key]) || $source[$key] === '') {
            return 0.0;
        }

        $raw = str_replace(',', '.', (string) wp_unslash((string) $source[$key]));
        $value = (float) $raw;
        if ($value < 0) {
            $value = 0.0;
        }

        return round($value, 2);
    }

    public static function integer(array $source, string $key, int $min = 0): int
    {
        if (!isset($source[$key]) || $source[$key] === '') {
            return max(0, $min);
        }

        $value = (int) wp_unslash((string) $source[$key]);
        if ($value < $min) {
            $value = $min;
        }

        return $value;
    }

    public static function boolean(array $source, string $key): bool
    {
        if (!isset($source[$key])) {
            return false;
        }

        return (bool) filter_var(
            wp_unslash((string) $source[$key]),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function sanitizeIdList($raw): array
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

    public static function validateProductPayload(array $post): array
    {
        $payload = [
            'nom'                    => self::text($post, 'nom', 255),
            'reference'              => self::text($post, 'reference', 255),
            'prix_achat'             => self::decimal($post, 'prix_achat'),
            'prix_vente'             => self::decimal($post, 'prix_vente'),
            'stock'                  => self::integer($post, 'stock', 0),
            'description'            => self::textarea($post, 'description'),
            'casier_emplacement'     => self::text($post, 'casier_emplacement', 120),
            'a_renseigner_plus_tard' => self::boolean($post, 'a_renseigner_plus_tard'),
            'categories'             => self::sanitizeIdList($post['categories'] ?? []),
            'tags'                   => self::sanitizeIdList($post['tags'] ?? []),
        ];

        if ($payload['nom'] === '' || $payload['reference'] === '') {
            throw new InventoryValidationException(
                'Merci de renseigner au minimum le nom et la référence.'
            );
        }

        return $payload;
    }

    public static function validateInlineUpdate(string $field, $value): array
    {
        $field = sanitize_key($field);
        $allowed = [
            'prix_achat'            => 'float',
            'prix_vente'            => 'float',
            'stock'                 => 'int',
            'casier_emplacement'    => 'string',
            'a_renseigner_plus_tard'=> 'bool',
        ];

        if (!isset($allowed[$field])) {
            throw new InventoryValidationException('Champ de mise à jour non autorisé.');
        }

        switch ($allowed[$field]) {
            case 'int':
                $clean = (int) $value;
                if ($clean < 0) {
                    $clean = 0;
                }
                break;
            case 'float':
                $clean = (float) str_replace(',', '.', (string) $value);
                if ($clean < 0) {
                    $clean = 0.0;
                }
                $clean = round($clean, 2);
                break;
            case 'string':
                $clean = sanitize_text_field((string) $value);
                $clean = mb_substr($clean, 0, 120);
                break;
            case 'bool':
                $clean = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                break;
            default:
                $clean = $value;
        }

        return [$field, $clean];
    }

    public static function validateCategoryPayload(array $post): array
    {
        $nom = self::text($post, 'nom', 150);
        if ($nom === '') {
            throw new InventoryValidationException('Le nom de la catégorie est obligatoire.');
        }

        $couleur = sanitize_hex_color((string) ($post['couleur'] ?? '')) ?: '#c47b83';
        $icone = self::text($post, 'icone', 50);
        $id = isset($post['id']) ? (int) wp_unslash((string) $post['id']) : 0;

        return [
            'id'      => $id,
            'nom'     => $nom,
            'couleur' => $couleur,
            'icone'   => $icone,
        ];
    }

    public static function validateTagPayload(array $post): array
    {
        $nom = self::text($post, 'nom', 150);
        if ($nom === '') {
            throw new InventoryValidationException('Le nom du tag est obligatoire.');
        }

        $id = isset($post['id']) ? (int) wp_unslash((string) $post['id']) : 0;

        return [
            'id'  => $id,
            'nom' => $nom,
        ];
    }
}

/**
 * Gestionnaire de l'upload d'image produit.
 */
final class InventoryMediaManager
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public static function handleUpload(array $files): ?string
    {
        if (empty($files['image']['name'])) {
            return null;
        }

        $file = $files['image'];
        if (!empty($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
            throw new InventoryValidationException("Erreur lors du téléchargement de l'image.");
        }

        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']);
        } else {
            $mime = $file['type'] ?? '';
        }

        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InventoryValidationException("Format d'image non supporté.");
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'jpg';
        }

        $imageName = uniqid('inv_', true) . '.' . $extension;
        $uploadDir = trailingslashit(get_stylesheet_directory()) . 'uploads/';

        if (!file_exists($uploadDir) && !wp_mkdir_p($uploadDir)) {
            throw new InventoryValidationException("Impossible de préparer le dossier d'upload.");
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
            throw new InventoryValidationException("Impossible de sauvegarder l'image.");
        }

        return $imageName;
    }
}

/**
 * Requêtes SQL liées aux catégories et tags.
 */
final class InventoryTaxonomyRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function syncProductTerms(int $productId, array $categoryIds, array $tagIds): void
    {
        $this->pdo->prepare('DELETE FROM produits_categories WHERE produit_id = :id')->execute([':id' => $productId]);
        if (!empty($categoryIds)) {
            $stmt = $this->pdo->prepare('INSERT INTO produits_categories (produit_id, categorie_id) VALUES (:produit_id, :categorie_id)');
            foreach ($categoryIds as $categoryId) {
                $stmt->execute([
                    ':produit_id'   => $productId,
                    ':categorie_id' => $categoryId,
                ]);
            }
        }

        $this->pdo->prepare('DELETE FROM produits_tags WHERE produit_id = :id')->execute([':id' => $productId]);
        if (!empty($tagIds)) {
            $stmt = $this->pdo->prepare('INSERT INTO produits_tags (produit_id, tag_id) VALUES (:produit_id, :tag_id)');
            foreach ($tagIds as $tagId) {
                $stmt->execute([
                    ':produit_id' => $productId,
                    ':tag_id'     => $tagId,
                ]);
            }
        }
    }

    public function enrichProductsWithTerms(array &$products): void
    {
        if (empty($products)) {
            return;
        }

        $productIds = array_filter(array_map(static function ($item) {
            return (int) ($item['id'] ?? 0);
        }, $products));

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

        $stmt = $this->pdo->prepare(
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

        $stmt = $this->pdo->prepare(
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
    }

    public function getTaxonomies(): array
    {
        $categories = [];
        $tags = [];

        $categoriesStmt = $this->pdo->query('SELECT id, nom, couleur, icone FROM categories ORDER BY nom ASC');
        if ($categoriesStmt) {
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $tagsStmt = $this->pdo->query('SELECT id, nom FROM tags ORDER BY nom ASC');
        if ($tagsStmt) {
            $tags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
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
        ];
    }

    public function saveCategory(array $data): array
    {
        if ($data['id'] > 0) {
            $stmt = $this->pdo->prepare('UPDATE categories SET nom = :nom, couleur = :couleur, icone = :icone WHERE id = :id');
            $stmt->execute([
                ':nom'     => $data['nom'],
                ':couleur' => $data['couleur'],
                ':icone'   => $data['icone'],
                ':id'      => $data['id'],
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO categories (nom, couleur, icone) VALUES (:nom, :couleur, :icone)');
            $stmt->execute([
                ':nom'     => $data['nom'],
                ':couleur' => $data['couleur'],
                ':icone'   => $data['icone'],
            ]);
            $data['id'] = (int) $this->pdo->lastInsertId();
        }

        return $data;
    }

    public function deleteCategory(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM produits_categories WHERE categorie_id = :id');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function saveTag(array $data): array
    {
        if ($data['id'] > 0) {
            $stmt = $this->pdo->prepare('UPDATE tags SET nom = :nom WHERE id = :id');
            $stmt->execute([
                ':nom' => $data['nom'],
                ':id'  => $data['id'],
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO tags (nom) VALUES (:nom)');
            $stmt->execute([
                ':nom' => $data['nom'],
            ]);
            $data['id'] = (int) $this->pdo->lastInsertId();
        }

        return $data;
    }

    public function deleteTag(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM produits_tags WHERE tag_id = :id');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('DELETE FROM tags WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function assignTerms(int $productId, array $categoryIds, array $tagIds): array
    {
        $this->syncProductTerms($productId, $categoryIds, $tagIds);
        $products = [['id' => $productId]];
        $this->enrichProductsWithTerms($products);
        $product = $products[0] ?? ['categories' => [], 'tags' => []];

        return [
            'categories' => $product['categories'] ?? [],
            'tags'       => $product['tags'] ?? [],
        ];
    }
}

/**
 * Couche métier des produits.
 */
final class InventoryProductRepository
{
    private PDO $pdo;
    private InventoryTaxonomyRepository $taxonomy;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->taxonomy = new InventoryTaxonomyRepository($pdo);
    }

    public function taxonomy(): InventoryTaxonomyRepository
    {
        return $this->taxonomy;
    }

    public function create(array $post, array $files, ?WP_User $user): void
    {
        $payload = InventoryRequestValidator::validateProductPayload($post);
        $imageName = null;

        if (!empty($files)) {
            $imageName = InventoryMediaManager::handleUpload($files);
        }

        $ajoutePar = 'Système';
        if ($user instanceof WP_User && $user->exists()) {
            $ajoutePar = $user->display_name ?: $user->user_login;
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'INSERT INTO produits (nom, reference, prix_achat, prix_vente, stock, description, image, casier_emplacement, a_renseigner_plus_tard, ajoute_par)
                 VALUES (:nom, :reference, :prix_achat, :prix_vente, :stock, :description, :image, :casier_emplacement, :a_renseigner_plus_tard, :ajoute_par)'
            );

            $stmt->execute([
                ':nom'                    => $payload['nom'],
                ':reference'              => $payload['reference'],
                ':prix_achat'             => $payload['prix_achat'],
                ':prix_vente'             => $payload['prix_vente'],
                ':stock'                  => $payload['stock'],
                ':description'            => $payload['description'],
                ':image'                  => $imageName,
                ':casier_emplacement'     => $payload['casier_emplacement'],
                ':a_renseigner_plus_tard' => $payload['a_renseigner_plus_tard'] ? 1 : 0,
                ':ajoute_par'             => $ajoutePar,
            ]);

            $productId = (int) $this->pdo->lastInsertId();
            $this->taxonomy->syncProductTerms($productId, $payload['categories'], $payload['tags']);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function getAll(array $filters): array
    {
        $categoryFilter = InventoryRequestValidator::sanitizeIdList($filters['categories'] ?? []);
        $tagFilter = InventoryRequestValidator::sanitizeIdList($filters['tags'] ?? []);

        $sql = 'SELECT DISTINCT p.* FROM produits p';
        $joins = [];
        $conditions = [];
        $params = [];

        if (!empty($categoryFilter)) {
            $joins[] = 'INNER JOIN produits_categories pc_filter ON pc_filter.produit_id = p.id';
            $conditions[] = 'pc_filter.categorie_id IN (' . implode(',', array_fill(0, count($categoryFilter), '?')) . ')';
            $params = array_merge($params, $categoryFilter);
        }

        if (!empty($tagFilter)) {
            $joins[] = 'INNER JOIN produits_tags pt_filter ON pt_filter.produit_id = p.id';
            $conditions[] = 'pt_filter.tag_id IN (' . implode(',', array_fill(0, count($tagFilter), '?')) . ')';
            $params = array_merge($params, $tagFilter);
        }

        if (!empty($joins)) {
            $sql .= ' ' . implode(' ', $joins);
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY p.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($products as &$row) {
            $row['prix_achat'] = isset($row['prix_achat']) ? (float) $row['prix_achat'] : 0.0;
            $row['prix_vente'] = isset($row['prix_vente']) ? (float) $row['prix_vente'] : 0.0;
            $row['stock'] = isset($row['stock']) ? (int) $row['stock'] : 0;
            $row['a_renseigner_plus_tard'] = isset($row['a_renseigner_plus_tard']) ? (int) $row['a_renseigner_plus_tard'] : 0;
            $row['casier_emplacement'] = isset($row['casier_emplacement']) ? (string) $row['casier_emplacement'] : '';
        }
        unset($row);

        $this->taxonomy->enrichProductsWithTerms($products);

        return $products;
    }

    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM produits_categories WHERE produit_id = :id');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('DELETE FROM produits_tags WHERE produit_id = :id');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('DELETE FROM produits WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query(
            'SELECT COALESCE(SUM(stock), 0) AS total_stock,
                    COALESCE(SUM(prix_achat * stock), 0) AS total_achat,
                    COALESCE(SUM(prix_vente * stock), 0) AS total_vente
             FROM produits'
        );

        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        $totalAchat = (float) ($result['total_achat'] ?? 0);
        $totalVente = (float) ($result['total_vente'] ?? 0);

        return [
            'total_articles' => (int) ($result['total_stock'] ?? 0),
            'valeur_achat'   => $totalAchat,
            'valeur_vente'   => $totalVente,
            'marge_totale'   => $totalVente - $totalAchat,
        ];
    }

    public function updateField(int $id, string $field, $value): array
    {
        if ($id <= 0) {
            throw new InventoryValidationException('Identifiant produit invalide.');
        }

        [$fieldKey, $cleanValue] = InventoryRequestValidator::validateInlineUpdate($field, $value);

        $stmt = $this->pdo->prepare("UPDATE produits SET {$fieldKey} = :value WHERE id = :id");
        $stmt->execute([
            ':value' => $cleanValue,
            ':id'    => $id,
        ]);

        return [
            'field' => $fieldKey,
            'value' => $cleanValue,
        ];
    }

    public function assignTerms(int $productId, array $categoryIds, array $tagIds): array
    {
        if ($productId <= 0) {
            throw new InventoryValidationException('Identifiant produit invalide.');
        }

        return $this->taxonomy->assignTerms($productId, $categoryIds, $tagIds);
    }
}

function inventory_debug_enabled(): bool
{
    return defined('WP_DEBUG') && WP_DEBUG;
}

function inventory_verify_request(): void
{
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Authentification requise.'], 401);
    }

    $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash((string) $_REQUEST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'inventory_nonce')) {
        wp_send_json_error(['message' => 'Requête non autorisée.'], 403);
    }
}

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
add_action('wp_ajax_inventory_get_taxonomies', 'inventory_handle_get_taxonomies');
add_action('wp_ajax_inventory_save_category', 'inventory_handle_save_category');
add_action('wp_ajax_inventory_delete_category', 'inventory_handle_delete_category');
add_action('wp_ajax_inventory_save_tag', 'inventory_handle_save_tag');
add_action('wp_ajax_inventory_delete_tag', 'inventory_handle_delete_tag');
add_action('wp_ajax_inventory_assign_terms', 'inventory_handle_assign_terms');

function inventory_handle_add_product(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    try {
        $repository->create($_POST, $_FILES, wp_get_current_user());
    } catch (InventoryValidationException $exception) {
        wp_send_json_error(['message' => $exception->getMessage()], 422);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Enregistrement impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success(['message' => 'Produit ajouté avec succès.']);
}

function inventory_handle_get_products(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    try {
        $filters = [
            'categories' => $_POST['categories'] ?? [],
            'tags'       => $_POST['tags'] ?? [],
        ];
        $products = $repository->getAll($filters);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Lecture des produits impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success($products);
}

function inventory_handle_delete_product(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => 'Identifiant invalide.'], 422);
    }

    try {
        $repository->delete($id);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Suppression impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success(['message' => 'Produit supprimé.']);
}

function inventory_handle_get_stats(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    try {
        $stats = $repository->getStats();
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Lecture des statistiques impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success($stats);
}

function inventory_handle_update_product(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    $field = isset($_POST['field']) ? (string) $_POST['field'] : '';
    $value = $_POST['value'] ?? null;

    try {
        $result = $repository->updateField($id, $field, $value);
    } catch (InventoryValidationException $exception) {
        wp_send_json_error(['message' => $exception->getMessage()], 422);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Mise à jour impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success(
        array_merge(['message' => 'Produit mis à jour.'], $result)
    );
}

function inventory_handle_get_taxonomies(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    try {
        $taxonomies = $repository->taxonomy()->getTaxonomies();
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Impossible de charger les taxonomies.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success($taxonomies);
}

function inventory_handle_save_category(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    try {
        $payload = InventoryRequestValidator::validateCategoryPayload($_POST);
        $category = $repository->taxonomy()->saveCategory($payload);
    } catch (InventoryValidationException $exception) {
        wp_send_json_error(['message' => $exception->getMessage()], 422);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => "Impossible d'enregistrer la catégorie.",
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message'  => 'Catégorie enregistrée.',
        'category' => $category,
    ]);
}

function inventory_handle_delete_category(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => 'Identifiant invalide.'], 422);
    }

    try {
        $repository->taxonomy()->deleteCategory($id);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Suppression de la catégorie impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success(['message' => 'Catégorie supprimée.']);
}

function inventory_handle_save_tag(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    try {
        $payload = InventoryRequestValidator::validateTagPayload($_POST);
        $tag = $repository->taxonomy()->saveTag($payload);
    } catch (InventoryValidationException $exception) {
        wp_send_json_error(['message' => $exception->getMessage()], 422);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => "Impossible d'enregistrer le tag.",
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success([
        'message' => 'Tag enregistré.',
        'tag'     => $tag,
    ]);
}

function inventory_handle_delete_tag(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    $id = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => 'Identifiant invalide.'], 422);
    }

    try {
        $repository->taxonomy()->deleteTag($id);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Suppression du tag impossible.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success(['message' => 'Tag supprimé.']);
}

function inventory_handle_assign_terms(): void
{
    inventory_verify_request();
    $pdo = inventory_require_pdo();
    $repository = new InventoryProductRepository($pdo);

    $productId = isset($_POST['id']) ? (int) wp_unslash((string) $_POST['id']) : 0;
    $categoryIds = InventoryRequestValidator::sanitizeIdList($_POST['categories'] ?? []);
    $tagIds = InventoryRequestValidator::sanitizeIdList($_POST['tags'] ?? []);

    try {
        $result = $repository->assignTerms($productId, $categoryIds, $tagIds);
    } catch (InventoryValidationException $exception) {
        wp_send_json_error(['message' => $exception->getMessage()], 422);
    } catch (Throwable $throwable) {
        wp_send_json_error(
            [
                'message' => 'Impossible de mettre à jour les associations.',
                'details' => inventory_debug_enabled() ? $throwable->getMessage() : null,
            ],
            500
        );
    }

    wp_send_json_success(
        array_merge(['message' => 'Associations mises à jour.'], $result)
    );
}
