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
                                <label for="product-location" class="form-label"><?php echo esc_html('Casier / Emplacement'); ?></label>
                                <input type="text" id="product-location" name="casier_emplacement" class="form-control" placeholder="Ex : A1, Bx1" maxlength="120" />
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

                        <div class="form-grid form-grid-terms">
                            <div class="form-group">
                                <label for="product-categories" class="form-label"><?php echo esc_html('Catégories'); ?></label>
                                <select id="product-categories" name="categories[]" class="form-control multi-select" multiple data-placeholder="<?php echo esc_attr('Sélectionnez des catégories'); ?>"></select>
                                <small class="form-hint"><?php echo esc_html('Affectez une ou plusieurs catégories colorées à votre pièce.'); ?></small>
                            </div>
                            <div class="form-group">
                                <label for="product-tags" class="form-label"><?php echo esc_html('Tags'); ?></label>
                                <select id="product-tags" name="tags[]" class="form-control multi-select" multiple data-placeholder="<?php echo esc_attr('Ajoutez des tags inspirants'); ?>"></select>
                                <small class="form-hint"><?php echo esc_html('Mots-clés libres pour affiner vos recherches.'); ?></small>
                            </div>
                        </div>

                        <div class="form-section">
                            <label for="product-description" class="form-label"><?php echo esc_html('Description'); ?></label>
                            <textarea id="product-description" name="description" class="form-control" rows="4" placeholder="Détails, matériaux, époque..."></textarea>
                        </div>

                        <aside class="follow-up-card" aria-labelledby="follow-up-title">
                            <div class="follow-up-header">
                                <div class="follow-up-icon" aria-hidden="true">📝</div>
                                <div class="follow-up-text">
                                    <span id="follow-up-title" class="follow-up-title"><?php echo esc_html('À renseigner plus tard'); ?></span>
                                    <p class="follow-up-description"><?php echo esc_html('Activez cette option pour vous rappeler qu’il manque des informations sur cet objet.'); ?></p>
                                </div>
                                <label class="follow-up-switch" for="product-follow-up">
                                    <input type="checkbox" id="product-follow-up" name="a_renseigner_plus_tard" value="1" />
                                    <span class="follow-up-slider" aria-hidden="true"></span>
                                    <span class="screen-reader-text"><?php echo esc_html('Marquer l’objet comme à compléter plus tard'); ?></span>
                                </label>
                            </div>
                            <ul class="follow-up-hints">
                                <li><?php echo esc_html('Les objets marqués affichent un badge orange dans l’inventaire.'); ?></li>
                                <li><?php echo esc_html('Pensez à compléter ces fiches pour ne rien oublier.'); ?></li>
                            </ul>
                        </aside>

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

                    <div class="inventory-filters">
                        <div class="filter-group">
                            <label for="filter-categories"><?php echo esc_html('Filtrer par catégories'); ?></label>
                            <select id="filter-categories" class="form-control multi-select" multiple data-placeholder="<?php echo esc_attr('Toutes les catégories'); ?>"></select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-tags"><?php echo esc_html('Filtrer par tags'); ?></label>
                            <select id="filter-tags" class="form-control multi-select" multiple data-placeholder="<?php echo esc_attr('Tous les tags'); ?>"></select>
                        </div>
                        <div class="filter-group">
                            <button type="button" id="reset-filters" class="inventory-button ghost-button"><?php echo esc_html('Réinitialiser'); ?></button>
                        </div>
                    </div>

                    <div class="inventory-table-wrapper">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html('Image'); ?></th>
                                    <th><?php echo esc_html('Objet'); ?></th>
                                    <th><?php echo esc_html('Casier'); ?></th>
                                    <th><?php echo esc_html('Prix achat (€)'); ?></th>
                                    <th><?php echo esc_html('Prix vente (€)'); ?></th>
                                    <th><?php echo esc_html('Stock'); ?></th>
                                    <th><?php echo esc_html('Marge'); ?></th>
                                    <th><?php echo esc_html('Suivi'); ?></th>
                                    <th><?php echo esc_html('Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="inventory-table-body">
                                <tr class="empty-state">
                                    <td colspan="9">
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

        <?php get_template_part('categories'); ?>

        <div id="inventory-term-modal" class="inventory-modal" aria-hidden="true">
            <div class="inventory-modal-overlay" role="presentation"></div>
            <div class="inventory-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="inventory-term-modal-title">
                <button type="button" class="modal-close" id="close-term-modal" aria-label="<?php echo esc_attr('Fermer la fenêtre'); ?>">&times;</button>
                <h3 id="inventory-term-modal-title"><?php echo esc_html('Catégories & tags du produit'); ?></h3>
                <p class="modal-subtitle"><?php echo esc_html('Ajustez les catégories colorées et les tags descriptifs pour affiner vos recherches.'); ?></p>
                <form id="inventory-term-form">
                    <input type="hidden" id="term-product-id" value="" />
                    <div class="modal-field">
                        <label for="modal-categories"><?php echo esc_html('Catégories associées'); ?></label>
                        <select id="modal-categories" class="form-control multi-select" multiple></select>
                    </div>
                    <div class="modal-field">
                        <label for="modal-tags"><?php echo esc_html('Tags associés'); ?></label>
                        <select id="modal-tags" class="form-control multi-select" multiple></select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="inventory-button ghost-button" id="cancel-term-modal"><?php echo esc_html('Annuler'); ?></button>
                        <button type="submit" class="inventory-button primary-button"><?php echo esc_html('Enregistrer'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="inventory-toast-stack" aria-live="polite" aria-atomic="true"></div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
