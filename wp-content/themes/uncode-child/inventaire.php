<?php
/**
 * Template Name: Inventaire Perso
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
        'appName'    => 'Inventaire perso',
    ]
);

get_header();
?>

<div class="inventory-page inventory-theme-neo">
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
        <?php $inventory_current_user = wp_get_current_user(); ?>
        <div class="inventory-wrapper">
            <nav class="inventory-navbar" aria-label="<?php echo esc_attr('Navigation Inventaire perso'); ?>">
                <div class="navbar-brand">
                    <span class="brand-logo" aria-hidden="true">IP</span>
                    <div class="brand-copy">
                        <span class="brand-title"><?php echo esc_html('Inventaire perso'); ?></span>
                        <span class="brand-subtitle"><?php echo esc_html('Bijoux & objets vintage multicanaux'); ?></span>
                    </div>
                </div>
                <div class="navbar-actions">
                    <button type="button" class="inventory-button primary-button" id="navbar-add-product"><?php echo esc_html('Ajouter un article'); ?></button>
                    <button type="button" class="inventory-button ghost-button" id="navbar-open-analytics"><?php echo esc_html('Ouvrir les KPI'); ?></button>
                    <?php if ($inventory_current_user instanceof WP_User && $inventory_current_user->exists()) : ?>
                        <div class="navbar-user" aria-label="<?php echo esc_attr('Utilisateur connecté'); ?>">
                            <span class="user-initials" aria-hidden="true"><?php echo esc_html(strtoupper(mb_substr($inventory_current_user->display_name ?: $inventory_current_user->user_login, 0, 2))); ?></span>
                            <span class="user-name"><?php echo esc_html($inventory_current_user->display_name ?: $inventory_current_user->user_login); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>
            <section class="inventory-hero" aria-labelledby="inventory-hero-title">
                <div class="hero-intro">
                    <span class="hero-kicker"><?php echo esc_html('Inventaire perso'); ?></span>
                    <h1 id="inventory-hero-title" class="hero-title"><?php echo esc_html('Pilotez vos pièces et vos ventes en un lieu unique'); ?></h1>
                    <p class="hero-description"><?php echo esc_html('Ajoutez un bijou, suivez vos stocks et surveillez vos marges pour Vinted, eBay ou Le Bon Coin en quelques clics.'); ?></p>
                    <div class="hero-actions">
                        <button type="button" class="inventory-button secondary-button" id="open-term-manager"><?php echo esc_html('Gérer les catégories & tags'); ?></button>
                        <button type="button" class="inventory-button ghost-button" id="scroll-to-inventory"><?php echo esc_html('Voir l\'inventaire'); ?></button>
                    </div>
                </div>
                <div class="hero-stats" id="inventory-hero-stats">
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
            </section>

            <div class="inventory-main-grid">
                <section class="inventory-card quick-add-card" id="quick-add-card">
                    <header class="quick-add-header">
                        <div>
                            <span class="quick-add-chip"><?php echo esc_html('Inventaire perso • Ajout express'); ?></span>
                            <h2><?php echo esc_html('Ajoutez une nouvelle pièce sans quitter l’écran'); ?></h2>
                            <p><?php echo esc_html('Capturez les détails clés, assignez les catégories et publiez sur vos plateformes de vente en quelques secondes.'); ?></p>
                        </div>
                        <div class="quick-add-shortcuts">
                            <button type="button" class="inventory-button ghost-button" id="quick-open-advanced" aria-expanded="false" aria-controls="inventory-advanced-fields">
                                <?php echo esc_html('Options avancées'); ?>
                            </button>
                        </div>
                    </header>

                    <form id="inventory-form" class="inventory-form" enctype="multipart/form-data">
                        <div class="quick-add-grid">
                            <div class="quick-add-media">
                                <div class="image-upload">
                                    <div class="image-preview-wrapper">
                                        <img id="image-preview" class="image-preview is-empty" src="" alt="<?php echo esc_attr('Aperçu de l\'image'); ?>" />
                                        <span class="image-placeholder"><?php echo esc_html('Ajoutez une jolie photo de votre pièce'); ?></span>
                                    </div>
                                    <label class="image-upload-button" for="product-image"><?php echo esc_html('Choisir une image'); ?></label>
                                    <input type="file" id="product-image" name="image" accept="image/*" />
                                </div>
                            </div>
                            <div class="quick-add-fields">
                                <div class="quick-add-microcopy">
                                    <strong><?php echo esc_html('Saisie express'); ?></strong>
                                    <p><?php echo esc_html('Commencez par les informations indispensables, vous pourrez enrichir la fiche ensuite.'); ?></p>
                                </div>
                                <div class="form-grid form-grid-compact">
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
                                </div>
                                <div class="form-grid form-grid-pricing">
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
                                <div class="follow-up-banner" aria-labelledby="follow-up-title">
                                    <div class="follow-up-content">
                                        <div class="follow-up-icon" aria-hidden="true">📝</div>
                                        <div>
                                            <span id="follow-up-title" class="follow-up-title"><?php echo esc_html('À renseigner plus tard'); ?></span>
                                            <p class="follow-up-description"><?php echo esc_html('Gardez en vue les fiches incomplètes grâce à un rappel visuel.'); ?></p>
                                        </div>
                                    </div>
                                    <label class="follow-up-switch" for="product-follow-up">
                                        <input type="checkbox" id="product-follow-up" name="a_renseigner_plus_tard" value="1" />
                                        <span class="follow-up-slider" aria-hidden="true"></span>
                                        <span class="screen-reader-text"><?php echo esc_html('Marquer l’objet comme à compléter plus tard'); ?></span>
                                    </label>
                                </div>
                                <button type="button" class="advanced-toggle" data-target="#inventory-advanced-fields" aria-expanded="false">
                                    <span class="toggle-icon" aria-hidden="true">➕</span>
                                    <span class="toggle-label"><?php echo esc_html('Afficher les options avancées'); ?></span>
                                </button>
                            </div>
                        </div>

                        <div id="inventory-advanced-fields" class="advanced-fields" hidden>
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
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="inventory-button primary-button"><?php echo esc_html('Ajouter à l\'inventaire'); ?></button>
                            <span class="form-footnote"><?php echo esc_html('Moins d\'une minute pour enrichir Inventaire perso.'); ?></span>
                        </div>
                    </form>
                </section>

                <section class="inventory-card inventory-board" id="inventory-board">
                    <div class="board-header">
                        <div>
                            <h2><?php echo esc_html('Inventaire perso en temps réel'); ?></h2>
                            <p><?php echo esc_html('Filtrez, cherchez, exportez et retrouvez vos annonces multicanales sans changer d’onglet.'); ?></p>
                        </div>
                        <div class="board-actions">
                            <input type="text" id="inventory-search" class="form-control search-input" placeholder="Rechercher un bijou..." aria-label="<?php echo esc_attr('Rechercher'); ?>" />
                            <button type="button" id="export-csv" class="inventory-button ghost-button"><?php echo esc_html('Exporter CSV'); ?></button>
                        </div>
                    </div>

                    <div class="board-filters">
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
