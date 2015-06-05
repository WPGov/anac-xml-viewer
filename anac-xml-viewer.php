<?php
/*
Plugin Name: ANAC XML Importer
Plugin URI: http://www.wpgov.it
Description: Visualizzatore XML per file generati da applicativi non-&copy;WPGov - Tutti i diritti riservati
Author: Marco Milesi
Version: 1.0.3
Author URI: http://www.marcomilesi.ml
GitHub Plugin URI: https://github.com/WPGov/anac-xml-viewer
GitHub Branch: master
*/

add_action( 'init', 'register_cpt_anacimporter' );

function register_cpt_anacimporter() {

    $labels = array(
        'name' => _x( 'Importa un dataset XML di terze parti (genera una tabella statica)', 'avcp' ),
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
        'menu_name' => _x( 'XML Esterni', 'avcp' ),
    );

    if ( is_admin && (!function_exists( 'is_plugin_active' ) ) ) {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
        if ( is_plugin_active( 'avcp/avcp.php' ) ) {
            $showinmenu = 'edit.php?post_type=avcp';
        } else {
            $showinmenu = true;
        }
    }

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'Gare AVCP',
        'supports' => array( 'title', 'editor'),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => $showinmenu,
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

function axv_init() {
    if ( !class_exists('Fragen\GitHub_Updater\Autoloader') ) {
       require_once(plugin_dir_path(__FILE__) . 'github/github-updater.php');
    }
}
add_action('admin_init', 'axv_init');
?>
