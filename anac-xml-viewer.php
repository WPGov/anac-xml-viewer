<?php
/*
Plugin Name: ANAC XML Viewer
Plugin URI: https://wordpress.org/plugins/anac-xml-viewer/
Description: Visualizzatore XML per file generati da applicativi esterni
Author: Marco Milesi
Version: 1.7.1
Author URI: https://marcomilesi.com
*/

add_action( 'init', 'register_cpt_anacimporter' );

function register_cpt_anacimporter() {

    $labels = array(
        'name' => 'Visualizzatore dataset XML Anac',
        'singular_name' => 'Dataset XML',
        'add_new' => 'Nuovo Dataset',
        'add_new_item' => 'Importa Nuovo Dataset',
        'edit_item' => 'Modifica Dataset',
        'new_item' => 'Nuovo Dataset',
        'view_item' => 'Vedi Dataset',
        'search_items' => 'Cerca Dataset',
        'not_found' => 'Nessuna voce trovata',
        'not_found_in_trash' => 'Nessuna voce trovata',
        'parent_item_colon' => 'Parent:',
        'menu_name' => 'Anac XML',
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => false,
        'description' => 'Gare AVCP',
        'supports' => array( 'title', 'editor'),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 37,
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

add_action( 'admin_notices', function() {
    global $current_screen;
    if ( 'anac-xml-view' == $current_screen->post_type  ) {
        echo '
        <div class="notice">
            <p>Puoi importare un file XML copiando e incollando il contenuto o inserendo un indirizzo URL completo. Tutorial: <a href="https://youtu.be/cdn082kZogk" target="_blank">youtu.be/cdn082kZogk</a></p>
        </div>';
    }
}  );

function axv_columns($columns) {
    $columns['atype'] = 'Dettagli';
    unset($columns['date']);
    $columns['date'] = 'Data';
    return $columns;
}
add_filter('manage_edit-anac-xml-view_columns', 'axv_columns');

add_filter('bulk_actions-edit-anac-xml-view', '__return_empty_array');

add_action('manage_anac-xml-view_posts_custom_column', 'axv_manage_columns', 10, 2);
function axv_manage_columns($column, $post_id)
{
    global $post;
    switch ($column) {
        case 'atype':
            if ( substr( get_post_field('post_content', $post_id), 0, 4 ) === "http" ) {
                printf(  '<a target="_blank" href="'.get_post_field('post_content', $post_id ).'">'.get_post_field('post_content', $post_id ).'</a><br>' );
            }
            printf( 'Shortcode: [anac-xml id="' . $post_id . '"]');
            break;
        default:
            break;
    }
}
?>
