<?php
/*
Plugin Name: ANAC XML Importer
Plugin URI: http://www.wpgov.it
Description: Visualizzatore XML per file generati da applicativi non-&copy;WPGov - Tutti i diritti riservati
Author: Marco Milesi
Version: 1.0
Author URI: http://www.marcomilesi.ml
GitHub Plugin URI: https://github.com/WPGov/anac-xml-viewer
*/

add_action( 'init', 'register_cpt_anacimporter' );

function register_cpt_anacimporter() {

    $labels = array(
        'name' => _x( 'XML Importati', 'avcp' ),
        'singular_name' => _x( 'Dataset XML', 'avcp' ),
        'add_new' => _x( 'Nuovo Dataset', 'avcp' ),
        'add_new_item' => _x( 'Nuovo Dataset', 'avcp' ),
        'edit_item' => _x( 'Modifica Dataset', 'avcp' ),
        'new_item' => _x( 'Nuovo Dataset', 'avcp' ),
        'view_item' => _x( 'Vedi Dataset', 'avcp' ),
        'search_items' => _x( 'Cerca Dataset', 'avcp' ),
        'not_found' => _x( 'Nessuna voce trovata', 'avcp' ),
        'not_found_in_trash' => _x( 'Nessuna voce trovata', 'avcp' ),
        'parent_item_colon' => _x( 'Parent:', 'avcp' ),
        'menu_name' => _x( 'XML Importati', 'avcp' ),
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'Gare AVCP',
        'supports' => array( 'title', 'editor'),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 39,
        'menu_icon'    => 'dashicons-list-view',
        'show_in_nav_menus' => false,
        'publicly_queryable' => true,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => false,
        'rewrite' => false
    );

    register_post_type( 'anac-xml-view', $args );
}
    require_once(plugin_dir_path(__FILE__) . 'core.php');
    require_once(plugin_dir_path(__FILE__) . 'github/github-updater.php');
?>
