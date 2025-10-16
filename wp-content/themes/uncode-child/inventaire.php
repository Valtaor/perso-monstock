<?php
/**
 * Template Name: Inventaire Bijoux
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style(
    'inventory-style',
    get_stylesheet_directory_uri() . '/style-inventaire.css',
    [],
    '1.0.0'
);

wp_enqueue_script(
    'inventory-script',
    get_stylesheet_directory_uri() . '/script-inventaire.js',
    ['jquery'],
    '1.0.0',
    true
);

wp_localize_script(
    'inventory-script',
    'inventorySettings',
    [
        'ajaxUrl' => get_stylesheet_directory_uri() . '/includes/functions_inventaire.php',
        'uploadsUrl' => get_stylesheet_directory_uri() . '/uploads/',
    ]
);

get_header();
?>

<div class="inventory-page">
    <?php if (!is_user_logged_in()) : ?>
        <section class="inventory-access-denied">
            <div class="inventory-card">
                <h2><?php esc_html_e('Accès restreint', 'uncode'); ?></h2>
                <p><?php esc_html_e('Vous devez être connecté pour consulter le tableau de bord inventaire.', 'uncode'); ?></p>
                <a class="inventory-button" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                    <?php esc_html_e('Se connecter', 'uncode'); ?>
                </a>
            </div>
        </section>
    <?php else : ?>
        <div class="inventory-wrapper">
            <header class="inventory-app-header">
                <div class="inventory-app-title">
                    <h1><?php esc_html_e('Tableau de bord inventaire', 'uncode'); ?></h1>
                    <span><?php esc_html_e('Visualisez vos stocks, ventes et marges en un coup d\'œil.', 'uncode'); ?></span>
                </div>
                <div class="inventory-app-actions">
                    <a class="inventory-button ghost-button" href="#inventory-form-card"><?php esc_html_e('Ajouter un bijou', 'uncode'); ?></a>
                    <button type="button" id="export-csv" class="inventory-button primary-button"><?php esc_html_e('Exporter CSV', 'uncode'); ?></button>
                </div>
            </header>

            <div class="inventory-shell">
                <aside class="inventory-nav" aria-label="<?php esc_attr_e('Navigation du tableau de bord', 'uncode'); ?>">
                    <div>
                        <h2><?php esc_html_e('Navigation', 'uncode'); ?></h2>
                        <div class="inventory-nav-section">
                            <a class="inventory-nav-link is-active" href="#inventory-overview">
                                <span class="icon" aria-hidden="true">📊</span>
                                <?php esc_html_e('Tableau de bord', 'uncode'); ?>
                            </a>
                            <a class="inventory-nav-link" href="#inventory-table">
                                <span class="icon" aria-hidden="true">📋</span>
                                <?php esc_html_e('Catalogue', 'uncode'); ?>
                            </a>
                            <a class="inventory-nav-link" href="#inventory-form-card">
                                <span class="icon" aria-hidden="true">➕</span>
                                <?php esc_html_e('Ajouter un bijou', 'uncode'); ?>
                            </a>
                        </div>
                    </div>
                    <div>
                        <h2><?php esc_html_e('Filtres rapides', 'uncode'); ?></h2>
                        <div class="inventory-quick-filters">
                            <button type="button" class="filter-chip" data-filter="recent">✨ <?php esc_html_e('Nouveautés', 'uncode'); ?></button>
                            <button type="button" class="filter-chip" data-filter="best-sellers">⭐ <?php esc_html_e('Meilleures ventes', 'uncode'); ?></button>
                            <button type="button" class="filter-chip" data-filter="low-stock">⚠️ <?php esc_html_e('Stock faible', 'uncode'); ?></button>
                        </div>
                    </div>
                </aside>

                <main class="inventory-main-content">
                    <section id="inventory-overview" class="inventory-card inventory-overview">
                        <div class="inventory-toolbar">
                            <div class="inventory-search-group">
                                <span class="search-icon" aria-hidden="true">🔍</span>
                                <input type="text" id="inventory-search" class="form-control search-input" placeholder="<?php esc_attr_e('Rechercher un bijou...', 'uncode'); ?>" aria-label="<?php esc_attr_e('Rechercher', 'uncode'); ?>" />
                            </div>
                            <div class="inventory-actions">
                                <button type="button" class="inventory-button ghost-button" data-range="30"><?php esc_html_e('30 derniers jours', 'uncode'); ?></button>
                                <button type="button" class="inventory-button ghost-button" data-range="90"><?php esc_html_e('90 derniers jours', 'uncode'); ?></button>
                            </div>
                        </div>

                        <div class="inventory-stats-grid">
                            <article class="stat-card">
                                <h3><?php esc_html_e('Articles en stock', 'uncode'); ?></h3>
                                <span id="stat-total-articles" class="stat-value">0</span>
                            </article>
                            <article class="stat-card">
                                <h3><?php esc_html_e('Valeur d\'achat', 'uncode'); ?></h3>
                                <span id="stat-valeur-achat" class="stat-value">0 €</span>
                            </article>
                            <article class="stat-card">
                                <h3><?php esc_html_e('Valeur de vente', 'uncode'); ?></h3>
                                <span id="stat-valeur-vente" class="stat-value">0 €</span>
                            </article>
                            <article class="stat-card">
                                <h3><?php esc_html_e('Marge estimée', 'uncode'); ?></h3>
                                <span id="stat-marge-totale" class="stat-value">0 €</span>
                            </article>
                        </div>
                    </section>

                    <div class="inventory-content-grid">
                        <section id="inventory-table" class="inventory-card inventory-table-card">
                            <div>
                                <h2><?php esc_html_e('Catalogue des pièces', 'uncode'); ?></h2>
                                <p><?php esc_html_e('Modifiez vos prix, stocks et exportez votre inventaire en quelques clics.', 'uncode'); ?></p>
                            </div>
                            <div class="inventory-table-wrapper">
                                <table class="inventory-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Image', 'uncode'); ?></th>
                                            <th><?php esc_html_e('Objet', 'uncode'); ?></th>
                                            <th><?php esc_html_e('Prix achat (€)', 'uncode'); ?></th>
                                            <th><?php esc_html_e('Prix vente (€)', 'uncode'); ?></th>
                                            <th><?php esc_html_e('Stock', 'uncode'); ?></th>
                                            <th><?php esc_html_e('Marge', 'uncode'); ?></th>
                                            <th><?php esc_html_e('Actions', 'uncode'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventory-table-body">
                                        <tr class="empty-state">
                                            <td colspan="7">
                                                <div class="empty-wrapper">
                                                    <span class="empty-icon">💎</span>
                                                    <p><?php esc_html_e('Aucun bijou dans l\'inventaire pour le moment.', 'uncode'); ?></p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="inventory-card inventory-breakdown-card">
                            <h3><?php esc_html_e('Points de vigilance', 'uncode'); ?></h3>
                            <div class="inventory-breakdown-list">
                                <div class="inventory-breakdown-item">
                                    <strong><?php esc_html_e('Stock faible', 'uncode'); ?></strong>
                                    <span id="stat-low-stock">0</span>
                                </div>
                                <div class="inventory-breakdown-item">
                                    <strong><?php esc_html_e('En rupture', 'uncode'); ?></strong>
                                    <span id="stat-out-of-stock">0</span>
                                </div>
                                <div class="inventory-breakdown-item">
                                    <strong><?php esc_html_e('Marge moyenne', 'uncode'); ?></strong>
                                    <span id="stat-average-margin">0 €</span>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section id="inventory-form-card" class="inventory-card inventory-form-card">
                        <h2 class="inventory-title"><?php esc_html_e('Ajouter un bijou ou un objet vintage', 'uncode'); ?></h2>
                        <p class="inventory-subtitle"><?php esc_html_e('Complétez les informations pour enrichir votre collection en quelques clics.', 'uncode'); ?></p>

                        <form id="inventory-form" class="inventory-form" enctype="multipart/form-data">
                            <div class="form-section">
                                <label for="product-image" class="form-label"><?php esc_html_e('Photo', 'uncode'); ?></label>
                                <div class="image-upload">
                                    <div class="image-preview-wrapper">
                                        <img id="image-preview" class="image-preview is-empty" src="" alt="<?php esc_attr_e('Aperçu de l\'image', 'uncode'); ?>" />
                                        <span class="image-placeholder"><?php esc_html_e('Ajoutez une jolie photo de votre pièce', 'uncode'); ?></span>
                                    </div>
                                    <label class="image-upload-button" for="product-image"><?php esc_html_e('Choisir une image', 'uncode'); ?></label>
                                    <input type="file" id="product-image" name="image" accept="image/*" />
                                </div>
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
                            </div>

                            <div class="form-section">
                                <label for="product-description" class="form-label"><?php esc_html_e('Description', 'uncode'); ?></label>
                                <textarea id="product-description" name="description" class="form-control" rows="4" placeholder="Détails, matériaux, époque..."></textarea>
                            </div>

                            <button type="submit" class="inventory-button primary-button"><?php esc_html_e('Ajouter à l\'inventaire', 'uncode'); ?></button>
                        </form>
                    </section>
                </main>
            </div>
        </div>

        <div class="inventory-toast-stack" aria-live="polite" aria-atomic="true"></div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
