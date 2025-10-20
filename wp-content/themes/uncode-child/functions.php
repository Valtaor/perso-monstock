<?php
add_action('after_setup_theme', 'uncode_language_setup');
function uncode_language_setup(): void
{
    load_child_theme_textdomain('uncode', get_stylesheet_directory() . '/languages');
}

function theme_enqueue_styles(): void
{
    $production_mode = ot_get_option('_uncode_production');
    $resources_version = ($production_mode === 'on') ? null : rand();
    if (function_exists('get_rocket_option') && (get_rocket_option('remove_query_strings') || get_rocket_option('minify_css') || get_rocket_option('minify_js'))) {
        $resources_version = null;
    }

    $parent_style = 'uncode-style';
    $child_style = ['uncode-style'];
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/library/css/style.css', [], $resources_version);
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', $child_style, $resources_version);
}
add_action('wp_enqueue_scripts', 'theme_enqueue_styles', 100);

// On inclut le fichier qui gère les requêtes AJAX de l'inventaire
// TOUTE la logique de chargement des scripts/styles pour l'inventaire
// est gérée via injection directe dans inventaire.php pour éviter les conflits.
require_once get_stylesheet_directory() . '/includes/functions_inventaire.php';

// Fin du fichier functions.php
?>
