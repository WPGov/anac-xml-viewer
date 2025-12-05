<?php
/*
Plugin Name: ANAC XML Viewer
Plugin URI: https://wordpress.org/plugins/anac-xml-viewer/
Description: Visualizzatore XML per file generati da applicativi esterni
Author: Marco Milesi
Version: 1.8.2
Author URI: https://marcomilesi.com
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class ANAC_XML_Viewer {

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'admin_notices', [ $this, 'admin_notice' ] );
        add_filter( 'manage_edit-anac-xml-view_columns', [ $this, 'columns' ] );
        add_filter( 'bulk_actions-edit-anac-xml-view', '__return_empty_array' );
        add_action( 'manage_anac-xml-view_posts_custom_column', [ $this, 'manage_columns' ], 10, 2 );
        add_shortcode( 'anac-xml', [ $this, 'shortcode' ] );
        add_shortcode( 'wpgov-xmlviewer', [ $this, 'shortcode' ] );
        add_filter( 'user_can_richedit', [ $this, 'disable_rich_edit' ] );
        add_filter( 'enter_title_here', [ $this, 'custom_title_placeholder' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'template_redirect', [ $this, 'template_redirect' ] );
    }

    public function register_cpt() {
        $labels = [
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
        ];
        $args = [
            'labels' => $labels,
            'hierarchical' => false,
            'description' => 'Gare AVCP',
            'supports' => [ 'title', 'editor' ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 37,
            'menu_icon' => 'dashicons-list-view',
            'show_in_nav_menus' => false,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'has_archive' => false,
            'query_var' => true,
            'can_export' => false,
            'rewrite' => false
        ];
        register_post_type( 'anac-xml-view', $args );
    }

    public function admin_notice() {
        global $current_screen;
        if ( isset($current_screen->post_type) && 'anac-xml-view' == $current_screen->post_type ) {
            echo '<div class="notice"><p>Puoi importare un file XML copiando e incollando il contenuto o inserendo un indirizzo URL completo. Tutorial: <a href="https://youtu.be/cdn082kZogk" target="_blank">youtu.be/cdn082kZogk</a></p></div>';
        }
    }

    public function columns( $columns ) {
        $columns['atype'] = 'Dettagli';
        unset($columns['date']);
        $columns['date'] = 'Data';
        return $columns;
    }

    public function manage_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'atype':
                $content = get_post_field( 'post_content', $post_id );
                if ( substr( $content, 0, 4 ) === "http" ) {
                    printf( '<a target="_blank" href="%s">%s</a><br>', esc_url($content), esc_html($content) );
                }
                printf( 'Shortcode: [anac-xml id="%d"]', $post_id );
                break;
        }
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 'all' ], $atts );
        ob_start();
        $this->generate_table( $atts['id'] );
        return ob_get_clean();
    }

    public function disable_rich_edit( $default ) {
        global $post;
        if ( isset($post) && 'anac-xml-view' == get_post_type($post) ) {
            add_filter( 'quicktags_settings', [ $this, 'quicktags_settings' ] );
            remove_action( 'media_buttons', 'media_buttons' );
            return false;
        }
        return $default;
    }

    public function quicktags_settings( $qtInit ) {
        $qtInit['buttons'] = 'fullscreen';
        return $qtInit;
    }

    public function custom_title_placeholder( $title ) {
        $screen = get_current_screen();
        if ( 'anac-xml-view' == $screen->post_type ) {
            $title = 'Nome o titolo del documento (es. "2023")';
        }
        return $title;
    }

    public function add_meta_boxes() {
        add_meta_box( 'anacxmlviewer_info', 'Informazioni', [ $this, 'info_metabox' ], 'anac-xml-view', 'side' );
        add_meta_box( 'anacxmlviewer_table', 'Anteprima tabella', [ $this, 'table_metabox' ], 'anac-xml-view', 'normal', 'default' );
    }

    public function info_metabox( $post, $metabox ) {
        echo 'Utilizza il seguente shortcode in un articolo o pagina di WordPress per visualizzare la tabella dei bandi di gara:<br><br><code>[anac-xml id="' . $post->ID . '"]</code>';
    }

    public function table_metabox( $post, $metabox ) {
        $this->generate_table( $post->ID );
    }

    public function generate_table( $id ) {
        remove_filter('the_content', 'wpautop');
        $content = get_post_field('post_content', $id);
        if ( empty($content) ) return;

        $time_start = microtime(true);

        if ( substr( $content, 0, 4 ) === "http" ) {
            $gare_xml = $this->fetch_and_load_xml( $content );
            if ( $gare_xml === null ) {
                echo '<div class="notice notice-error"><p>Impossibile caricare XML. Assicurarsi che l\'URL esista e che il contenuto sia XML valido.</p></div>';
                return;
            }
        } else {
            try {
                $gare_xml = new SimpleXMLElement( stripslashes($content) );
            } catch ( Exception $e ) {
                echo '<div class="notice notice-error"><p>Il contenuto inserito non è XML valido.</p></div>';
                return;
            }
        }

        echo '<script type="text/javascript" src="' . plugin_dir_url(__FILE__) . 'includes/excellentexport.min.js"></script>';

        $anno = isset($gare_xml->metadata->annoRiferimento) ? esc_html($gare_xml->metadata->annoRiferimento) : '';
        echo '<strong>' . esc_html( $gare_xml->metadata->entePubblicatore ) . '</strong><br><small>Aggiornato al ' . date("d.m.Y", strtotime($gare_xml->metadata->dataUltimoAggiornamentoDataset)) . '
            <br>URL originale: <a href="' . esc_url( $gare_xml->metadata->urlFile ) . '" target="_blank">' . esc_url( $gare_xml->metadata->urlFile ) . '</a></small><br>
    <table class="widefat data-table" id="gare">
        <thead>
            <tr>
                <td colspan="5">
                    Bandi di gara - <strong>' . $anno . '</strong>
                    <input style="float:right;" type="search" id="s" class="light-table-filter" data-table="data-table" placeholder="Cerca...">
                </td>
            </tr>
            <tr>
                <th class="row-title">CIG</th>
                <th>Oggetto</th>
                <th>Importo aggiudicazione</th>
                <th>Importo somme liquidate</th>
                <th>Data inizio<br>Data fine</th>
            </tr>
        </thead>
        <tbody>';

        $tot_agg = 0.00;
        $tot_liq = 0.00;
        $tot_lotti = 0;
        $a = '';

        foreach ( $gare_xml->xpath('//lotto') as $lotto ) {
            $tot_agg += (double)$lotto->importoAggiudicazione;
            $tot_liq += (double)$lotto->importoSommeLiquidate;
            $tot_lotti++;

            $a = ($a == '') ? ' class="alternate"' : '';

            $dataInizio = $lotto->tempiCompletamento->dataInizio ? date("d/m/Y", strtotime($lotto->tempiCompletamento->dataInizio)) : 'N.D.';
            $dataUltimazione = $lotto->tempiCompletamento->dataUltimazione ? date("d/m/Y", strtotime($lotto->tempiCompletamento->dataUltimazione)) : 'N.D.';

            echo '<tr' . $a . '>
                <td class="row-title"><label for="tablecell">' . esc_html( $lotto->cig ) . '</label></td>
                <td>' . esc_html( $lotto->oggetto ) . '</td>
                <td> ' . number_format((double)$lotto->importoAggiudicazione, 2, ',', '.') . '</td>
                <td> ' . number_format((double)$lotto->importoSommeLiquidate, 2, ',', '.') . '</td>
                <td>' . $dataInizio . '<br>' . $dataUltimazione . '</td>
                </tr><tr><td></td><td colspan="4"><small>' . esc_html( $lotto->sceltaContraente ) . '<br>Partecipanti:<br>';
            foreach ( $lotto->partecipanti->partecipante as $partecipante ) {
                echo esc_html( $partecipante->ragioneSociale ) . ' (' . esc_html( $partecipante->codiceFiscale ) . ')<br>';
            }
            echo 'Aggiudicatari:<br>';
            foreach ( $lotto->aggiudicatari->aggiudicatario as $aggiudicatario ) {
                echo esc_html( $aggiudicatario->ragioneSociale ) . ' (' . esc_html( $aggiudicatario->codiceFiscale ) . ')<br>';
            }
            echo '</small></td></tr>';
        }
        echo '<tfoot>
                  <tr>
                    <td>Totali</td>
                    <td>Numero Lotti: <strong>' . number_format((double)$tot_lotti, 0, ',', '.') . '</strong></td>
                    <td>' . number_format((double)$tot_agg, 2, ',', '.') . '</td>
                    <td>' . number_format((double)$tot_liq, 2, ',', '.') . '</td>
                    <td></td>
                  </tr>
                  <tr>
                    <td colspan="2">
                        <a href="https://wpgov.it" target="_blank" title="WordPress per la Pubblica Amministrazione">
            <img style="float: left;margin: 4px 5px;" src="' . plugins_url('wpgov.png', __FILE__) . '" ></a>
                        Tabella generata in <b>' . number_format( microtime(true) - $time_start, 3) . ' secondi</b>
                    </td>
                    <td colspan="3" style="text-align:right;">';

        echo 'Scarica in <a href="' . esc_url( $gare_xml->metadata->urlFile ) . '" target="_blank" title="File .xml"><button>XML</button></a>
                <a download="' . get_bloginfo('name') . '-gare' . $anno . '.xls" href="#" onclick="return ExcellentExport.excel(this, \'gare\', \'Gare\');"><button>EXCEL</button></a>
                <a download="' . get_bloginfo('name') . '-gare' . $anno . '.csv" href="#" onclick="return ExcellentExport.csv(this, \'gare\');"><button>CSV</button></a>';

        echo '</td></tr>
                </tfoot>';

        echo '</tbody></table>';
        echo '<div class="clear"></div>';
        ?>
        <script>
        (function(document) {
            'use strict';
            var LightTableFilter = (function(Arr) {
                var _input;
                function _onInputEvent(e) {
                    _input = e.target;
                    var tables = document.getElementsByClassName(_input.getAttribute('data-table'));
                    Arr.forEach.call(tables, function(table) {
                        Arr.forEach.call(table.tBodies, function(tbody) {
                            Arr.forEach.call(tbody.rows, _filter);
                        });
                    });
                }
                function _filter(row) {
                    var text = row.textContent.toLowerCase(), val = _input.value.toLowerCase();
                    row.style.display = text.indexOf(val) === -1 ? 'none' : 'table-row';
                }
                return {
                    init: function() {
                        var inputs = document.getElementsByClassName('light-table-filter');
                        Arr.forEach.call(inputs, function(input) {
                            input.oninput = _onInputEvent;
                        });
                    }
                };
            })(Array.prototype);
            document.addEventListener('readystatechange', function() {
                if (document.readyState === 'complete') {
                    LightTableFilter.init();
                }
            });
        })(document);
        </script>
        <?php
    }

    private function is_private_ip( $ip ) {
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $long = ip2long($ip);
            $ranges = [
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.168.0.0', '192.168.255.255']
            ];
            foreach ( $ranges as $r ) {
                if ( $long >= ip2long($r[0]) && $long <= ip2long($r[1]) ) return true;
            }
            return false;
        }
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            if ( strpos($ip, '::1') === 0 ) return true; // localhost
            // fc00::/7 unique local, fe80::/10 link-local
            if ( strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0 ) return true;
            if ( strpos($ip, 'fe80') === 0 ) return true;
            return false;
        }
        return true;
    }

    private function fetch_and_load_xml( $url ) {
        if ( ! function_exists('wp_http_validate_url') || ! wp_http_validate_url( $url ) ) return null;
        $parts = wp_parse_url( $url );
        if ( ! $parts || ! isset($parts['scheme']) || ! in_array( strtolower($parts['scheme']), ['http','https'], true ) ) return null;
        if ( empty($parts['host']) ) return null;

        $host = $parts['host'];
        $blocked_hosts = [
            '169.254.169.254',
            'metadata.google.internal'
        ];
        if ( in_array( strtolower($host), $blocked_hosts, true ) ) return null;

        $resolved = gethostbynamel( $host );
        if ( $resolved ) {
            foreach ( $resolved as $ip ) {
                if ( $this->is_private_ip( $ip ) ) return null;
            }
        }

        $args = [
            'timeout' => 5,
            'redirection' => 2,
            'headers' => [ 'Accept' => 'application/xml, text/xml; q=0.9, */*; q=0.1' ],
        ];
        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) return null;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) return null;

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) return null;

        $ctype = wp_remote_retrieve_header( $response, 'content-type' );
        if ( $ctype && strpos( strtolower($ctype), 'xml' ) === false ) {
            // Allow if body looks like XML even when header is wrong
            if ( strpos( ltrim($body), '<') !== 0 ) return null;
        }

        libxml_use_internal_errors(true);
        try {
            $xml = new SimpleXMLElement( $body );
            return $xml;
        } catch ( Exception $e ) {
            return null;
        }
    }

    public function template_redirect() {
        global $wp, $wp_query;
        if ( isset($wp->query_vars['post_type']) && $wp->query_vars['post_type'] == 'anac-xml-view' ) {
            if ( have_posts() ) {
                add_filter( 'the_content', [ $this, 'template_filter' ] );
            } else {
                $wp_query->is_404 = true;
            }
        }
    }

    public function template_filter( $content ) {
        global $wp_query;
        ob_start();
        $this->generate_table( $wp_query->post->ID );
        return ob_get_clean();
    }
}

new ANAC_XML_Viewer();