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
            <div class="inventory-columns">
                <section class="inventory-card inventory-form-card">
                    <h1 class="inventory-title"><?php esc_html_e('Ajouter un bijou ou un objet vintage', 'uncode'); ?></h1>
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

                <section class="inventory-card inventory-data-card">
                    <div class="inventory-data-header">
                        <div>
                            <h2><?php esc_html_e('Aperçu de l\'inventaire', 'uncode'); ?></h2>
                            <p><?php esc_html_e('Statistiques mises à jour en temps réel pour piloter vos ventes.', 'uncode'); ?></p>
                        </div>
                        <div class="inventory-actions">
                            <input type="text" id="inventory-search" class="form-control search-input" placeholder="Rechercher un bijou..." aria-label="<?php esc_attr_e('Rechercher', 'uncode'); ?>" />
                            <button type="button" id="export-csv" class="inventory-button ghost-button"><?php esc_html_e('Exporter CSV', 'uncode'); ?></button>
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
            </div>
        </div>

        <div class="inventory-toast-stack" aria-live="polite" aria-atomic="true"></div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
