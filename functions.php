<?php
add_action('after_setup_theme', 'uncode_language_setup');
function uncode_language_setup()
{
	load_child_theme_textdomain('uncode', get_stylesheet_directory() . '/languages');
}

function theme_enqueue_styles()
{
	$production_mode = ot_get_option('_uncode_production');
	$resources_version = ($production_mode === 'on') ? null : rand();
	$parent_style = 'uncode-style';
	$child_style = array('uncode-style');
	wp_enqueue_style($parent_style, get_template_directory_uri() . '/library/css/style.css', array(), $resources_version);
	wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', $child_style, $resources_version);
}
add_action('wp_enqueue_scripts', 'theme_enqueue_styles', 100);

/**
 * Charger les fichiers pour la page d'inventaire.
 * C'est la seule fonction nécessaire et elle est maintenant correcte.
 */
function uncode_child_enqueue_inventory_assets() {
    // On charge les assets UNIQUEMENT si on est sur la page utilisant le template "inventaire.php"
    if ( is_page_template('inventaire.php') ) {
        
        $assetVersion = time(); // Force la mise à jour du cache
        
        // Charger le fichier de style
        wp_enqueue_style(
            'inventory-style', 
            get_stylesheet_directory_uri() . '/style-inventaire.css', 
            [], 
            $assetVersion
        );
        
        // Charger le fichier de script
        wp_enqueue_script(
            'inventory-script', 
            get_stylesheet_directory_uri() . '/script-inventaire.js', 
            ['jquery'], 
            $assetVersion, 
            true // Charger dans le footer
        );
        
        // Passer les données de PHP à JavaScript
        wp_localize_script('inventory-script', 'inventorySettings', [
            // CORRECTION CRUCIALE : Utiliser l'URL AJAX standard de WordPress
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'uploadsUrl' => content_url('uploads/'),
            'i18n'       => [
                'deleteConfirm' => __('Supprimer cet article ? Cette action est irréversible.', 'uncode'),
                'toastAddSuccess' => __('Produit ajouté avec succès.', 'uncode'),
                'toastAddError' => __('Erreur lors de l\'ajout du produit.', 'uncode'),
                // Vous pouvez ajouter d'autres traductions ici
            ],
        ]);
    }
}
// On accroche notre fonction au bon endroit dans WordPress
add_action('wp_enqueue_scripts', 'uncode_child_enqueue_inventory_assets');

// On inclut le fichier qui gère les requêtes AJAX
require_once get_stylesheet_directory() . '/includes/functions_inventaire.php';