<?php
/**
 * Template Name: Inventaire Bijoux
 * Version finale, nettoyée et corrigée.
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité : interdit l'accès direct au fichier
}

// La logique de chargement des styles et scripts est maintenant dans functions.php.
// Nous nous assurons ici que les données pour le JavaScript sont correctes.

// On prépare les données à envoyer au script JavaScript
wp_localize_script(
    'inventory-script', // Le nom du script que nous ciblons (défini dans functions.php)
    'inventorySettings', // Le nom de l'objet JavaScript qui contiendra nos données
    [
        // CORRECTION CRUCIALE : Utiliser l'URL AJAX standard de WordPress
        'ajaxUrl' => admin_url('admin-ajax.php'),
        
        // Un chemin plus robuste pour les images uploadées
        'uploadsUrl' => content_url('uploads/'), 
        
        // Toutes vos traductions pour le JavaScript
        'i18n' => [
            'emptyInventory' => __('Votre inventaire est vide.', 'uncode'),
            'emptySearch' => __('Aucun bijou dans l\'inventaire pour le moment.', 'uncode'),
            'toastAddSuccess' => __('Produit ajouté avec succès.', 'uncode'),
            'toastAddError' => __('Erreur lors de l\'ajout du produit.', 'uncode'),
            'toastDeleteSuccess' => __('Produit supprimé.', 'uncode'),
            'toastDeleteError' => __('Suppression impossible.', 'uncode'),
            'toastUpdateSuccess' => __('Valeur mise à jour.', 'uncode'),
            'toastUpdateError' => __('Mise à jour impossible.', 'uncode'),
            'toggleIncomplete' => __('Marquer comme à compléter', 'uncode'),
            'toggleComplete' => __('Marquer comme complet', 'uncode'),
            'deleteConfirm' => __('Supprimer cet article ? Cette action est irréversible.', 'uncode'),
            'statusOutOfStock' => __('Rupture', 'uncode'),
            'statusInStock' => __('En stock', 'uncode'),
            'statusIncomplete' => __('À compléter', 'uncode'),
            'columnPhoto' => __('Photo', 'uncode'),
            'columnTitle' => __('Titre', 'uncode'),
            'columnCasier' => __('Casier', 'uncode'),
            'columnInfos' => __('Infos', 'uncode'),
            'columnQuantity' => __('Quantité', 'uncode'),
            'columnFollowUp' => __('Suivi', 'uncode'),
            'columnStatus' => __('Statut', 'uncode'),
            'columnActions' => __('Actions', 'uncode'),
            'labelPurchase' => __('Achat', 'uncode'),
            'labelSale' => __('Vente', 'uncode'),
            'labelDate' => __('Acheté le', 'uncode'),
            'filterAllCasiers' => __('Tous les casiers', 'uncode'),
            'filterAllStatus' => __('Tous les statuts', 'uncode'),
            'loadError' => __('Impossible de charger les produits.', 'uncode'),
            'markedIncomplete' => __('Objet marqué à compléter.', 'uncode'),
            'markedComplete' => __('Objet marqué complet.', 'uncode'),
            'submitLabel' => __('Ajouter à l\'inventaire', 'uncode'),
        ],
    ]
);

// On charge l'en-tête de votre site WordPress (ce qui inclut la balise <head> et le chargement des CSS)
get_header();
?>

<div class="inventory-page dashboard-v9">
    <?php if (!is_user_logged_in()) : ?>
        <section class="inventory-access-denied">
            <div class="inventory-card">
                <h2><?php esc_html_e('Accès restreint', 'uncode'); ?></h2>
                <p><?php esc_html_e('Vous devez être connecté pour consulter le tableau de bord inventaire.', 'uncode'); ?></p>
                <a class="inventory-button primary-button" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                    <?php esc_html_e('Se connecter', 'uncode'); ?>
                </a>
            </div>
        </section>
    <?php else : ?>
        <main class="inventory-dashboard" role="main">
            <header class="inventory-hero" aria-labelledby="inventory-overview-title">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="<?php esc_attr_e('Basculer le thème', 'uncode'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>
                <p class="inventory-kicker"><?php esc_html_e('Tableau de bord', 'uncode'); ?></p>
                <h1 id="inventory-overview-title" class="inventory-title"><?php esc_html_e('Gestion des stocks de Bijoux', 'uncode'); ?></h1>
                <p class="inventory-subtitle"><?php esc_html_e('Votre assistant intelligent pour suivre vos pièces, marges et fiches à compléter.', 'uncode'); ?></p>
            </header>

            <div class="inventory-container">
                <section class="form-section card" aria-labelledby="inventory-form-title">
                    <h2 class="card-title" id="inventory-form-title">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <?php esc_html_e('Ajouter un objet', 'uncode'); ?>
                    </h2>
                    <form id="inventory-form" class="inventory-form" enctype="multipart/form-data" method="post">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product-image" class="form-label"><?php esc_html_e('Photo', 'uncode'); ?></label>
                                <div class="file-upload-wrapper" role="presentation">
                                    <input type="file" id="product-image" name="image" accept="image/*" />
                                    <div class="file-upload-text">
                                        <p><?php esc_html_e('Glissez-déposez une image ou', 'uncode'); ?><br><span><?php esc_html_e('cliquez pour parcourir', 'uncode'); ?></span></p>
                                    </div>
                                </div>
                                <div class="photo-preview" id="photoPreviewContainer">
                                    <img id="image-preview" class="image-preview is-empty" src="" alt="<?php esc_attr_e('Aperçu de l\'image sélectionnée', 'uncode'); ?>" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="product-name" class="form-label"><?php esc_html_e('Titre de l\'objet', 'uncode'); ?></label>
                                <input type="text" id="product-name" name="nom" class="form-control" placeholder="<?php esc_attr_e('Bague art déco, broche vintage...', 'uncode'); ?>" required />
                            </div>
                            <div class="form-group">
                                <label for="product-reference" class="form-label"><?php esc_html_e('Référence', 'uncode'); ?></label>
                                <input type="text" id="product-reference" name="reference" class="form-control" placeholder="REF-001" required />
                            </div>
                            <div class="form-group">
                                <label for="product-location" class="form-label"><?php esc_html_e('Casier / emplacement', 'uncode'); ?></label>
                                <input type="text" id="product-location" name="emplacement" class="form-control" placeholder="Ex. A1, B1" />
                            </div>
                            <div class="form-group">
                                <label for="product-stock" class="form-label"><?php esc_html_e('Quantité', 'uncode'); ?></label>
                                <input type="number" min="0" id="product-stock" name="stock" class="form-control" placeholder="1" value="1" />
                            </div>
                            <div class="form-group">
                                <label for="product-prix-achat" class="form-label"><?php esc_html_e('Prix d\'achat (€)', 'uncode'); ?></label>
                                <input type="number" step="0.01" min="0" id="product-prix-achat" name="prix_achat" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="form-group">
                                <label for="product-prix-vente" class="form-label"><?php esc_html_e('Prix de vente (€)', 'uncode'); ?></label>
                                <input type="number" step="0.01" min="0" id="product-prix-vente" name="prix_vente" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="form-group">
                                <label for="product-date" class="form-label"><?php esc_html_e('Date d\'achat', 'uncode'); ?></label>
                                <input type="date" id="product-date" name="date_achat" class="form-control" />
                            </div>
                            <div class="form-group">
                                <label for="product-notes" class="form-label"><?php esc_html_e('Notes (facultatif)', 'uncode'); ?></label>
                                <textarea id="product-notes" name="notes" class="form-control" rows="3" placeholder="<?php esc_attr_e('État, provenance...', 'uncode'); ?>"></textarea>
                            </div>
                            <div class="form-group full-width follow-up-group">
                                <div class="follow-up-top">
                                    <label class="follow-up-text" for="product-incomplete" id="completeLaterLabel">
                                        <span class="follow-up-title"><?php esc_html_e('À renseigner plus tard', 'uncode'); ?></span>
                                        <span class="follow-up-description" id="completeLaterHelp"><?php esc_html_e('Activez cette option pour vous rappeler qu\'il manque des informations à compléter.', 'uncode'); ?></span>
                                    </label>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="product-incomplete" name="a_completer" value="1" aria-labelledby="completeLaterLabel" aria-describedby="completeLaterHelp" />
                                        <span class="toggle-slider" aria-hidden="true"></span>
                                    </label>
                                </div>
                                <div class="follow-up-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span><?php esc_html_e('Les objets marqués affichent un badge « À compléter » dans votre inventaire.', 'uncode'); ?></span>
                                </div>
                                <label for="product-description" class="form-label"><?php esc_html_e('Informations manquantes', 'uncode'); ?></label>
                                <textarea id="product-description" name="description" class="form-control" rows="3" placeholder="<?php esc_attr_e('Certificat, expertise, à vérifier...', 'uncode'); ?>"></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="secondary" id="cancel-edit" hidden><?php esc_html_e('Annuler', 'uncode'); ?></button>
                            <button type="submit" class="primary" id="submit-button"><?php esc_html_e('Ajouter à l\'inventaire', 'uncode'); ?></button>
                        </div>
                    </form>
                </section>

                <section class="dashboard-grid">
                    <div class="card" id="statsCard">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                            <?php esc_html_e('Statistiques', 'uncode'); ?>
                        </h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h3><?php esc_html_e('Articles en stock', 'uncode'); ?></h3>
                                <div class="value" id="stat-total-articles">0</div>
                            </div>
                            <div class="stat-card">
                                <h3><?php esc_html_e('En rupture', 'uncode'); ?></h3>
                                <div class="value" id="stat-out-of-stock">0</div>
                            </div>
                            <div class="stat-card">
                                <h3><?php esc_html_e('Valeur de vente', 'uncode'); ?></h3>
                                <div class="value" id="stat-valeur-vente">0,00 €</div>
                            </div>
                            <div class="stat-card">
                                <h3><?php esc_html_e('Marge', 'uncode'); ?></h3>
                                <div class="value" id="stat-marge-totale">0,00 €</div>
                            </div>
                        </div>
                        <span id="stat-valeur-achat" class="sr-only">0,00 €</span>
                        <div class="insights-grid">
                            <div class="insight-card">
                                <span class="insight-title"><?php esc_html_e('Stock faible', 'uncode'); ?></span>
                                <span class="insight-value" id="stat-low-stock">0</span>
                            </div>
                            <div class="insight-card">
                                <span class="insight-title"><?php esc_html_e('Fiches à compléter', 'uncode'); ?></span>
                                <span class="insight-value" id="stat-incomplete">0</span>
                            </div>
                            <div class="insight-card">
                                <span class="insight-title"><?php esc_html_e('Marge moyenne', 'uncode'); ?></span>
                                <span class="insight-value" id="stat-average-margin">0,00 €</span>
                            </div>
                        </div>
                    </div>

                    <div class="card" id="inventoryCard">
                        <h2 class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                            </svg>
                            <?php esc_html_e('Inventaire', 'uncode'); ?>
                        </h2>
                        <div class="quick-filters" id="quickFilters">
                            <button type="button" class="quick-filter-btn" data-status-filter="all" aria-pressed="true"><?php esc_html_e('Tout', 'uncode'); ?></button>
                            <button type="button" class="quick-filter-btn" data-status-filter="en-stock" aria-pressed="false"><?php esc_html_e('En stock', 'uncode'); ?></button>
                            <button type="button" class="quick-filter-btn" data-status-filter="rupture" aria-pressed="false"><?php esc_html_e('Rupture', 'uncode'); ?></button>
                            <button type="button" class="quick-filter-btn" data-status-filter="incomplet" aria-pressed="false"><?php esc_html_e('À compléter', 'uncode'); ?></button>
                        </div>
                        <div class="filters" id="filtersPanel">
                            <input type="search" id="inventory-search" class="search-input" placeholder="<?php esc_attr_e('Rechercher...', 'uncode'); ?>" />
                            <select id="filterCasier"><option value="all"><?php esc_html_e('Tous les casiers', 'uncode'); ?></option></select>
                            <select id="filterStatus"><option value="all"><?php esc_html_e('Tous les statuts', 'uncode'); ?></option></select>
                            <button type="button" class="secondary" id="export-csv"><?php esc_html_e('Exporter CSV', 'uncode'); ?></button>
                        </div>
                        <div class="table-wrapper">
                            <table class="inventory-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Photo', 'uncode'); ?></th>
                                        <th scope="col"><?php esc_html_e('Titre', 'uncode'); ?></th>
                                        <th scope="col"><?php esc_html_e('Infos', 'uncode'); ?></th>
                                        <th scope="col"><?php esc_html_e('Quantité', 'uncode'); ?></th>
                                        <th scope="col"><?php esc_html_e('Suivi', 'uncode'); ?></th>
                                        <th scope="col"><?php esc_html_e('Statut', 'uncode'); ?></th>
                                        <th scope="col"><?php esc_html_e('Actions', 'uncode'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-table-body"></tbody>
                            </table>
                            <div class="empty-state" id="empty-state">
                                <h3><?php esc_html_e('Votre inventaire est vide.', 'uncode'); ?></h3>
                                <p><?php esc_html_e('Commencez par ajouter votre premier objet !', 'uncode'); ?></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="inventory-toast-stack" aria-live="polite" aria-atomic="true"></div>
        </main>
    <?php endif; ?>
</div>

<?php 
// On charge le pied de page de votre site (ce qui inclut le chargement des scripts JS via wp_footer)
get_footer(); 
?>