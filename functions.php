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
	if ( function_exists('get_rocket_option') && ( get_rocket_option( 'remove_query_strings' ) || get_rocket_option( 'minify_css' ) || get_rocket_option( 'minify_js' ) ) ) {
		$resources_version = null;
	}
	$parent_style = 'uncode-style';
	$child_style = array('uncode-style');
	wp_enqueue_style($parent_style, get_template_directory_uri() . '/library/css/style.css', array(), $resources_version);
	wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', $child_style, $resources_version);
}
add_action('wp_enqueue_scripts', 'theme_enqueue_styles', 100);

require_once get_stylesheet_directory() . '/includes/functions_inventaire.php';
/**
 * Charger les styles et scripts pour la page d'inventaire.
 * C'est la méthode correcte et robuste pour WordPress.
 */
function uncode_child_enqueue_inventory_assets() {
    // On charge les assets UNIQUEMENT si on est sur la page utilisant le template "inventaire.php"
    if ( is_page_template('inventaire.php') ) {
        
        $assetVersion = '6.0.0'; // Version majeure pour forcer la mise à jour du cache
        
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
            true // true = charger dans le footer
        );
        
        // Passer les données de PHP à JavaScript
        wp_localize_script('inventory-script', 'inventorySettings', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'uploadsUrl' => content_url('uploads/inventory/'),
            'i18n'       => [
                'deleteConfirm' => __('Supprimer cet article ? Cette action est irréversible.', 'uncode'),
                'updateSuccess' => __('Valeur mise à jour.', 'uncode'),
                'updateError'   => __('Mise à jour impossible.', 'uncode'),
                'addSuccess'    => __('Produit ajouté.', 'uncode'),
                'addError'      => __('Erreur lors de l\'ajout.', 'uncode'),
                'deleteSuccess' => __('Produit supprimé.', 'uncode'),
                'deleteError'   => __('Suppression impossible.', 'uncode'),
            ],
        ]);
    }
}
// On accroche notre fonction au hook 'wp_enqueue_scripts' de WordPress.
add_action('wp_enqueue_scripts', 'uncode_child_enqueue_inventory_assets');
/**
 * TEST DE FORÇAGE : Charger les styles de l'inventaire sur TOUT le site
 * pour vérifier si le chargement fonctionne.
 */
function force_inventory_assets() {
    // Utilise l'heure actuelle pour garantir qu'il n'y a pas de mise en cache
    $assetVersion = time(); 
    
    // On charge le fichier de style
    wp_enqueue_style(
        'inventory-style-force', // Nom unique pour ce test
        get_stylesheet_directory_uri() . '/style-inventaire.css', 
        [], 
        $assetVersion
    );
    
    // On charge le fichier de script
    wp_enqueue_script(
        'inventory-script-force', // Nom unique pour ce test
        get_stylesheet_directory_uri() . '/script-inventaire.js', 
        ['jquery'], 
        $assetVersion, 
        true
    );
    
    // On passe les données
    wp_localize_script('inventory-script-force', 'inventorySettings', [
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'uploadsUrl' => content_url('uploads/inventory/'),
        'i18n'       => [ 'deleteConfirm' => __('Supprimer cet article ?', 'uncode') ]
    ]);
}
// On accroche la fonction SANS condition avec une haute priorité
add_action('wp_enqueue_scripts', 'force_inventory_assets', 999);