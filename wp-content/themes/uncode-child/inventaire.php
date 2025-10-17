<?php
/**
 * Template Name: Inventaire Bijoux
 */

if (!defined('ABSPATH')) {
    exit;
}

$assetVersion = '2.0.0';

wp_enqueue_style(
    'inventory-style',
    get_stylesheet_directory_uri() . '/style-inventaire.css',
    [],
    $assetVersion
);

wp_enqueue_script(
    'inventory-script',
    get_stylesheet_directory_uri() . '/script-inventaire.js',
    ['jquery'],
    $assetVersion,
    true
);

wp_localize_script(
    'inventory-script',
    'inventorySettings',
    [
        'ajaxUrl' => get_stylesheet_directory_uri() . '/includes/functions_inventaire.php',
        'uploadsUrl' => get_stylesheet_directory_uri() . '/uploads/',
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
            'deleteConfirm' => __('Supprimer cet article ?', 'uncode'),
        ],
    ]
);

get_header();
?>

<div class="inventory-page portrait-layout">
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
            <div class="inventory-shell">
                <header class="inventory-header" aria-labelledby="inventory-overview-title">
                    <div class="inventory-header-body">
                        <p class="inventory-kicker"><?php esc_html_e('Mon tableau de bord', 'uncode'); ?></p>
                        <h1 id="inventory-overview-title" class="inventory-title"><?php esc_html_e('Inventaire bijoux', 'uncode'); ?></h1>
                        <p class="inventory-subtitle"><?php esc_html_e('Gérez vos pièces, suivez les marges et complétez vos fiches depuis une interface portrait fidèle au modèle fourni.', 'uncode'); ?></p>
                    </div>
                    <div class="inventory-metrics" role="list">
                        <article class="metric-card" role="listitem">
                            <span class="metric-label"><?php esc_html_e('Articles en stock', 'uncode'); ?></span>
                            <span id="stat-total-articles" class="metric-value">0</span>
                        </article>
                        <article class="metric-card" role="listitem">
                            <span class="metric-label"><?php esc_html_e('Valeur de vente', 'uncode'); ?></span>
                            <span id="stat-valeur-vente" class="metric-value">0,00 €</span>
                        </article>
                        <article class="metric-card" role="listitem">
                            <span class="metric-label"><?php esc_html_e('Marge totale', 'uncode'); ?></span>
                            <span id="stat-marge-totale" class="metric-value">0,00 €</span>
                        </article>
                        <span id="stat-valeur-achat" class="sr-only">0,00 €</span>
                    </div>
                </header>

                <div class="inventory-body">
                    <section class="inventory-column inventory-column-primary">
                        <article id="inventory-form-card" class="inventory-card inventory-form-card" aria-labelledby="inventory-form-title">
                            <header class="inventory-card-header">
                                <div>
                                    <h2 id="inventory-form-title"><?php esc_html_e('Ajouter un bijou', 'uncode'); ?></h2>
                                    <p><?php esc_html_e('Importez la photo et complétez les informations essentielles de la pièce.', 'uncode'); ?></p>
                                </div>
                            </header>

                            <form id="inventory-form" class="inventory-form" enctype="multipart/form-data">
                                <div class="inventory-upload">
                                    <label for="product-image" class="upload-drop-area">
                                        <span class="upload-icon" aria-hidden="true">📷</span>
                                        <span class="upload-text"><?php esc_html_e('Déposez une photo', 'uncode'); ?></span>
                                        <span class="upload-subtext"><?php esc_html_e('ou cliquez pour parcourir vos fichiers', 'uncode'); ?></span>
                                        <div class="image-preview-wrapper">
                                            <img id="image-preview" class="image-preview is-empty" src="" alt="<?php esc_attr_e('Aperçu de l\'image sélectionnée', 'uncode'); ?>" />
                                            <span class="image-placeholder"><?php esc_html_e('Aperçu disponible après sélection', 'uncode'); ?></span>
                                        </div>
                                    </label>
                                    <input type="file" id="product-image" name="image" accept="image/*" />
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="product-name" class="form-label"><?php esc_html_e('Nom de l\'objet', 'uncode'); ?></label>
                                        <input type="text" id="product-name" name="nom" class="form-control" placeholder="Bague art déco, broche vintage..." required />
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
                                        <label for="product-prix-achat" class="form-label"><?php esc_html_e('Prix d\'achat (€)', 'uncode'); ?></label>
                                        <input type="number" step="0.01" min="0" id="product-prix-achat" name="prix_achat" class="form-control" placeholder="0.00" />
                                    </div>
                                    <div class="form-group">
                                        <label for="product-prix-vente" class="form-label"><?php esc_html_e('Prix de vente (€)', 'uncode'); ?></label>
                                        <input type="number" step="0.01" min="0" id="product-prix-vente" name="prix_vente" class="form-control" placeholder="0.00" />
                                    </div>
                                    <div class="form-group">
                                        <label for="product-stock" class="form-label"><?php esc_html_e('Stock disponible', 'uncode'); ?></label>
                                        <input type="number" min="0" id="product-stock" name="stock" class="form-control" placeholder="1" />
                                    </div>
                                    <div class="form-group">
                                        <label for="product-date" class="form-label"><?php esc_html_e('Date d\'achat', 'uncode'); ?></label>
                                        <input type="date" id="product-date" name="date_achat" class="form-control" />
                                    </div>
                                    <div class="form-group">
                                        <label for="product-notes" class="form-label"><?php esc_html_e('Notes (facultatif)', 'uncode'); ?></label>
                                        <input type="text" id="product-notes" name="notes" class="form-control" placeholder="Matériaux, état..." />
                                    </div>
                                </div>

                                <section class="form-section inventory-follow-up" aria-labelledby="follow-up-title">
                                    <div class="follow-up-header">
                                        <div>
                                            <h3 id="follow-up-title"><?php esc_html_e('À renseigner plus tard', 'uncode'); ?></h3>
                                            <p><?php esc_html_e('Activez l’alerte si la fiche nécessite des compléments.', 'uncode'); ?></p>
                                        </div>
                                        <label class="inventory-switch">
                                            <input type="checkbox" id="product-incomplete" name="a_completer" value="1" />
                                            <span class="switch-slider" aria-hidden="true"></span>
                                            <span class="sr-only"><?php esc_html_e('Marquer cet objet comme à compléter plus tard', 'uncode'); ?></span>
                                        </label>
                                    </div>
                                    <ul class="follow-up-hints">
                                        <li><?php esc_html_e('Un badge « À compléter » est visible dans la liste.', 'uncode'); ?></li>
                                        <li><?php esc_html_e('Ajoutez des précisions pour vos rappels.', 'uncode'); ?></li>
                                    </ul>
                                    <label for="product-description" class="form-label"><?php esc_html_e('Informations manquantes', 'uncode'); ?></label>
                                    <textarea id="product-description" name="description" class="form-control" rows="3" placeholder="Certificat d'authenticité, expertise, documents..."></textarea>
                                </section>

                                <div class="form-actions">
                                    <button type="submit" class="inventory-button primary-button"><?php esc_html_e('Ajouter à l\'inventaire', 'uncode'); ?></button>
                                </div>
                            </form>
                        </article>

                        <section class="inventory-card inventory-alert-card" aria-labelledby="inventory-alert-title">
                            <header class="inventory-card-header">
                                <div>
                                    <h2 id="inventory-alert-title"><?php esc_html_e('Points de vigilance', 'uncode'); ?></h2>
                                    <p><?php esc_html_e('Repérez instantanément les priorités du jour.', 'uncode'); ?></p>
                                </div>
                            </header>
                            <div class="inventory-alert-grid">
                                <div class="inventory-alert">
                                    <span class="alert-label"><?php esc_html_e('Stock faible', 'uncode'); ?></span>
                                    <span id="stat-low-stock" class="alert-value">0</span>
                                </div>
                                <div class="inventory-alert">
                                    <span class="alert-label"><?php esc_html_e('En rupture', 'uncode'); ?></span>
                                    <span id="stat-out-of-stock" class="alert-value">0</span>
                                </div>
                                <div class="inventory-alert">
                                    <span class="alert-label"><?php esc_html_e('Fiches à compléter', 'uncode'); ?></span>
                                    <span id="stat-incomplete" class="alert-value">0</span>
                                </div>
                                <div class="inventory-alert">
                                    <span class="alert-label"><?php esc_html_e('Marge moyenne', 'uncode'); ?></span>
                                    <span id="stat-average-margin" class="alert-value">0,00 €</span>
                                </div>
                            </div>
                        </section>
                    </section>

                    <section class="inventory-column inventory-column-secondary">
                        <article class="inventory-card inventory-table-card" id="inventory-table" aria-labelledby="inventory-table-title">
                            <header class="inventory-card-header">
                                <div>
                                    <h2 id="inventory-table-title"><?php esc_html_e('Inventaire détaillé', 'uncode'); ?></h2>
                                    <p><?php esc_html_e('Filtrez, exportez et mettez à jour vos fiches en direct.', 'uncode'); ?></p>
                                </div>
                                <button type="button" id="export-csv" class="inventory-button ghost-button"><?php esc_html_e('Exporter CSV', 'uncode'); ?></button>
                            </header>

                            <div class="inventory-table-tools">
                                <div class="inventory-search">
                                    <span class="search-icon" aria-hidden="true">🔍</span>
                                    <input type="text" id="inventory-search" class="form-control search-input" placeholder="<?php esc_attr_e('Rechercher un bijou...', 'uncode'); ?>" aria-label="<?php esc_attr_e('Rechercher', 'uncode'); ?>" />
                                </div>
                                <div class="inventory-legend">
                                    <span class="legend-item">
                                        <span class="status-badge badge-incomplete"><?php esc_html_e('À compléter', 'uncode'); ?></span>
                                        <span><?php esc_html_e('Fiche à compléter', 'uncode'); ?></span>
                                    </span>
                                </div>
                            </div>

                            <div class="inventory-table-wrapper">
                                <table class="inventory-table">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php esc_html_e('Image', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Objet', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Référence', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Casier', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Prix achat (€)', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Prix vente (€)', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Stock', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Marge', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Statut', 'uncode'); ?></th>
                                            <th scope="col"><?php esc_html_e('Actions', 'uncode'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventory-table-body">
                                        <tr class="empty-state">
                                            <td colspan="10">
                                                <div class="empty-wrapper">
                                                    <span class="empty-icon" aria-hidden="true">💎</span>
                                                    <p><?php esc_html_e('Votre inventaire est vide.', 'uncode'); ?></p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                </div>

                <div class="inventory-toast-stack" aria-live="polite" aria-atomic="true"></div>
            </div>
        </main>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
