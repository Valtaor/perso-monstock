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
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('inventory_nonce'),
        'uploadsUrl' => trailingslashit(get_stylesheet_directory_uri() . '/uploads'),
    ]
);

get_header();
?>

<div class="inventory-page">
    <?php if (!is_user_logged_in()) : ?>
        <section class="inventory-access-denied">
            <div class="inventory-card">
                <h2><?php echo esc_html('Accès restreint'); ?></h2>
                <p><?php echo esc_html('Vous devez être connecté pour consulter le tableau de bord inventaire.'); ?></p>
                <a class="inventory-button" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                    <?php echo esc_html('Se connecter'); ?>
                </a>
            </div>
        </section>
    <?php else : ?>
        <div class="inventory-wrapper">
            <div class="inventory-columns">
                <section class="inventory-card inventory-form-card">
                    <h1 class="inventory-title"><?php echo esc_html('Ajouter un bijou ou un objet vintage'); ?></h1>
                    <p class="inventory-subtitle"><?php echo esc_html('Complétez les informations pour enrichir votre collection en quelques clics.'); ?></p>

                    <form id="inventory-form" class="inventory-form" enctype="multipart/form-data">
                        <div class="form-section">
                            <label for="product-image" class="form-label"><?php echo esc_html('Photo'); ?></label>
                            <div class="image-upload">
                                <div class="image-preview-wrapper">
                                    <img id="image-preview" class="image-preview is-empty" src="" alt="<?php echo esc_attr('Aperçu de l\'image'); ?>" />
                                    <span class="image-placeholder"><?php echo esc_html('Ajoutez une jolie photo de votre pièce'); ?></span>
                                </div>
                                <label class="image-upload-button" for="product-image"><?php echo esc_html('Choisir une image'); ?></label>
                                <input type="file" id="product-image" name="image" accept="image/*" />
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product-name" class="form-label"><?php echo esc_html('Nom de l\'objet'); ?></label>
                                <input type="text" id="product-name" name="nom" class="form-control" placeholder="Bague art déco, broche vintage..." required />
                            </div>
                            <div class="form-group">
                                <label for="product-reference" class="form-label"><?php echo esc_html('Référence'); ?></label>
                                <input type="text" id="product-reference" name="reference" class="form-control" placeholder="REF-001" required />
                            </div>
                            <div class="form-group">
                                <label for="product-prix-achat" class="form-label"><?php echo esc_html('Prix d\'achat (€)'); ?></label>
                                <input type="number" step="0.01" min="0" id="product-prix-achat" name="prix_achat" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="form-group">
                                <label for="product-prix-vente" class="form-label"><?php echo esc_html('Prix de vente (€)'); ?></label>
                                <input type="number" step="0.01" min="0" id="product-prix-vente" name="prix_vente" class="form-control" placeholder="0.00" />
                            </div>
                            <div class="form-group">
                                <label for="product-stock" class="form-label"><?php echo esc_html('Stock disponible'); ?></label>
                                <input type="number" min="0" id="product-stock" name="stock" class="form-control" placeholder="1" />
                            </div>
                        </div>

                        <div class="form-section">
                            <label for="product-description" class="form-label"><?php echo esc_html('Description'); ?></label>
                            <textarea id="product-description" name="description" class="form-control" rows="4" placeholder="Détails, matériaux, époque..."></textarea>
                        </div>

                        <button type="submit" class="inventory-button primary-button"><?php echo esc_html('Ajouter à l\'inventaire'); ?></button>
                    </form>
                </section>

                <section class="inventory-card inventory-data-card">
                    <div class="inventory-data-header">
                        <div>
                            <h2><?php echo esc_html('Aperçu de l\'inventaire'); ?></h2>
                            <p><?php echo esc_html('Statistiques mises à jour en temps réel pour piloter vos ventes.'); ?></p>
                        </div>
                        <div class="inventory-actions">
                            <input type="text" id="inventory-search" class="form-control search-input" placeholder="Rechercher un bijou..." aria-label="<?php echo esc_attr('Rechercher'); ?>" />
                            <button type="button" id="export-csv" class="inventory-button ghost-button"><?php echo esc_html('Exporter CSV'); ?></button>
                        </div>
                    </div>

                    <div class="inventory-stats-grid">
                        <article class="stat-card">
                            <h3><?php echo esc_html('Articles en stock'); ?></h3>
                            <span id="stat-total-articles" class="stat-value">0</span>
                        </article>
                        <article class="stat-card">
                            <h3><?php echo esc_html('Valeur d\'achat'); ?></h3>
                            <span id="stat-valeur-achat" class="stat-value">0 €</span>
                        </article>
                        <article class="stat-card">
                            <h3><?php echo esc_html('Valeur de vente'); ?></h3>
                            <span id="stat-valeur-vente" class="stat-value">0 €</span>
                        </article>
                        <article class="stat-card">
                            <h3><?php echo esc_html('Marge estimée'); ?></h3>
                            <span id="stat-marge-totale" class="stat-value">0 €</span>
                        </article>
                    </div>

                    <div class="inventory-table-wrapper">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html('Image'); ?></th>
                                    <th><?php echo esc_html('Objet'); ?></th>
                                    <th><?php echo esc_html('Prix achat (€)'); ?></th>
                                    <th><?php echo esc_html('Prix vente (€)'); ?></th>
                                    <th><?php echo esc_html('Stock'); ?></th>
                                    <th><?php echo esc_html('Marge'); ?></th>
                                    <th><?php echo esc_html('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="inventory-table-body">
                                <tr class="empty-state">
                                    <td colspan="7">
                                        <div class="empty-wrapper">
                                            <span class="empty-icon">💎</span>
                                            <p><?php echo esc_html('Aucun bijou dans l\'inventaire pour le moment.'); ?></p>
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
