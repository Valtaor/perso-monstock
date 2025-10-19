<?php
/**
 * AJAX handlers for the inventory dashboard - CORRECTED for WordPress Standard AJAX.
 */
declare(strict_types=1);

// Inclure la connexion à la base de données
require_once __DIR__ . '/db_connect.php';

// --- Enregistrement des actions AJAX de WordPress ---
// On lie chaque 'action' envoyée par le JavaScript à une fonction PHP.
add_action('wp_ajax_get_products', 'handle_get_products_wp');
add_action('wp_ajax_add_product', 'handle_add_product_wp');
add_action('wp_ajax_update_product', 'handle_update_product_wp');
add_action('wp_ajax_delete_product', 'handle_delete_product_wp');

// --- FONCTIONS AJAX "WRAPPERS" ---
// Chaque fonction ci-dessous gère une requête AJAX spécifique.

function inventory_ajax_precheck() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Authentification requise.'], 401);
    }
    
    global $pdo;
    if (!$pdo instanceof PDO) {
        wp_send_json_error(['message' => 'Connexion à la base de données impossible.'], 500);
    }

    return $pdo;
}

function handle_get_products_wp() {
    $pdo = inventory_ajax_precheck();
    try {
        $supportsIncomplete = ensure_incomplete_flag($pdo);
        $stmt = $pdo->query('SELECT * FROM produits ORDER BY id DESC');
        $products = [];
        while ($row = $stmt->fetch()) {
            $products[] = $row;
        }
        // Utilise la fonction standard de WordPress pour renvoyer une réponse JSON réussie
        wp_send_json_success($products);
    } catch (PDOException $e) {
        wp_send_json_error(['message' => 'Erreur de base de données: ' . $e->getMessage()], 500);
    }
}

function handle_add_product_wp() {
    $pdo = inventory_ajax_precheck();
    try {
        handle_add_product($pdo, ensure_incomplete_flag($pdo));
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()], 400);
    }
}

function handle_update_product_wp() {
    $pdo = inventory_ajax_precheck();
    try {
        handle_update_product($pdo, ensure_incomplete_flag($pdo));
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()], 400);
    }
}

function handle_delete_product_wp() {
    $pdo = inventory_ajax_precheck();
    try {
        handle_delete_product($pdo);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()], 400);
    }
}


// --- FONCTIONS "MÉTIER" (Logique principale, peu modifiée) ---
// Ces fonctions sont maintenant appelées par les wrappers ci-dessus.

function ensure_incomplete_flag(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM produits LIKE 'a_completer'");
        return $stmt && $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function inventory_handle_upload(): ?string {
    if (empty($_FILES['image']['name'])) {
        return null;
    }
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erreur lors du téléchargement de l'image.");
    }

    // Le reste de la logique de validation et de déplacement du fichier...
    // Pour simplifier, on suppose que le dossier /uploads/inventory/ existe et est accessible en écriture.
    $upload_dir = wp_upload_dir();
    $inventory_dir = $upload_dir['basedir'] . '/inventory';
    if (!is_dir($inventory_dir)) {
        wp_mkdir_p($inventory_dir);
    }

    $filename = uniqid('inv_', true) . '-' . basename($file['name']);
    $destination = $inventory_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Impossible de sauvegarder l'image.");
    }
    return 'inventory/' . $filename; // On retourne le chemin relatif
}

function handle_add_product(PDO $pdo, bool $supportsIncomplete): void {
    // La logique reste similaire, mais renvoie des exceptions au lieu de JSON
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $reference = trim((string) ($_POST['reference'] ?? ''));
    if (empty($nom) || empty($reference)) {
        throw new Exception('Merci de renseigner le nom et la référence.');
    }
    
    // ... (le reste de la logique pour récupérer les variables POST)
    $imageName = inventory_handle_upload(); // Note: Cette fonction a besoin d'être adaptée pour utiliser les chemins WordPress
    
    $currentUser = wp_get_current_user();
    $ajoutePar = ($currentUser->exists()) ? $currentUser->display_name : 'Système';
    
    $params = [
        ':nom' => $nom,
        ':reference' => $reference,
        ':prix_achat' => (float) ($_POST['prix_achat'] ?? 0.0),
        ':prix_vente' => (float) ($_POST['prix_vente'] ?? 0.0),
        ':stock' => max(0, (int) ($_POST['stock'] ?? 0)),
        ':description' => trim((string) ($_POST['description'] ?? '')),
        ':image' => $imageName,
        ':ajoute_par' => $ajoutePar,
        ':a_completer' => (isset($_POST['a_completer']) && $_POST['a_completer'] == '1') ? 1 : 0,
    ];

    $sql = "INSERT INTO produits (nom, reference, prix_achat, prix_vente, stock, description, image, ajoute_par, a_completer) VALUES (:nom, :reference, :prix_achat, :prix_vente, :stock, :description, :image, :ajoute_par, :a_completer)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    wp_send_json_success(['message' => 'Produit ajouté avec succès.']);
}

function handle_update_product(PDO $pdo, bool $supportsIncomplete): void {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $field = isset($_POST['field']) ? (string) $_POST['field'] : '';
    $value = $_POST['value'] ?? null;

    $allowedFields = ['prix_achat', 'prix_vente', 'stock', 'a_completer'];

    if ($id <= 0 || !in_array($field, $allowedFields)) {
        throw new Exception('Paramètres invalides.');
    }

    $stmt = $pdo->prepare("UPDATE produits SET {$field} = :value WHERE id = :id");
    $stmt->execute([':value' => $value, ':id' => $id]);

    wp_send_json_success(['message' => 'Produit mis à jour.']);
}

function handle_delete_product(PDO $pdo): void {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Identifiant invalide.');
    }
    
    $stmt = $pdo->prepare('DELETE FROM produits WHERE id = :id');
    $stmt->execute([':id' => $id]);

    wp_send_json_success(['message' => 'Produit supprimé.']);
}
?>