<?php

add_shortcode('anac-xml', function($atts) {
    extract(shortcode_atts(array('id' => 'all'), $atts));
    ob_start();
    anacxmlviewer_generatabella($id);
    $anacxmlshortcode = ob_get_clean();
    return $anacxmlshortcode;
} );

add_shortcode('wpgov-xmlviewer', function($atts) {
    extract(shortcode_atts(array('id' => 'all'), $atts));
    ob_start();
    anacxmlviewer_generatabella($id);
    $anacxmlshortcode = ob_get_clean();
    return $anacxmlshortcode;
} );

add_filter('user_can_richedit', function($default) {
    global $post;
    if ('anac-xml-view' == get_post_type($post)) {
        add_filter('quicktags_settings', 'anacxmlviewer_47010');
        remove_action('media_buttons', 'media_buttons');
        return false;
    }
    return $default;
});

add_filter('enter_title_here', function( $title ) {
    $screen = get_current_screen();
    if ( 'anac-xml-view' == $screen->post_type ) {
        $title = 'Nome o titolo del documento (es. "2023")';
    }
    return $title;
} );

function anacxmlviewer_47010($qtInit) {
    $qtInit['buttons'] = 'fullscreen';
    return $qtInit;
}
add_action('add_meta_boxes', function() {
    add_meta_box('anacxmlviewer_info', 'Informazioni', 'anacxmlviewer_metabox_callback', 'anac-xml-view', 'side');
});

function anacxmlviewer_metabox_callback ($post, $metabox) {
    global $post;
    echo 'Utilizza il seguente shortcode in un articolo o pagina di WordPress per visualizzare la tabella dei bandi di gara:<br><br><code>[anac-xml id="' . $post->ID . '"]</code>';
}

function add_anacxmlviewer_table_box() {
    add_meta_box('anacxmlviewer_table', 'Anteprima tabella', 'anacxmlviewer_table_callback', 'anac-xml-view', 'normal', 'default');
}
function anacxmlviewer_table_callback($post, $metabox) {
    anacxmlviewer_generatabella($post->ID);
}

function anacxmlviewer_generatabella($id) {
    remove_filter('the_content', 'wpautop');
    if (get_post_field('post_content', $id) == null) {
        return;
    }
    $time_start = microtime(true);

    if ( substr( get_post_field('post_content', $id), 0, 4 ) === "http" ) {
        $gare_xml = new SimpleXMLElement( get_post_field('post_content', $id), LIBXML_NOCDATA, true);
    } else {
        $gare_xml = new SimpleXMLElement(stripslashes(get_post_field('post_content', $id)));
    }

    echo '<script type="text/javascript" src="'.plugin_dir_url(__FILE__).'includes/excellentexport.min.js"></script>';

    echo '<strong>' . esc_html( $gare_xml->metadata->entePubblicatore ) . '</strong><br><small>Aggiornato al ' . date("d.m.Y", strtotime($gare_xml->metadata->dataUltimoAggiornamentoDataset)) . '
        <br>URL originale: <a href="' . esc_url( $gare_xml->metadata->urlFile ) . '" target="_blank">' . esc_url( $gare_xml->metadata->urlFile ) . '</a></small><br>
<table class="widefat data-table" id="gare">
    <thead>
        <tr>
            <td colspan="5">
                Bandi di gara - <strong>'.esc_html( $gare_xml->metadata->annoRiferimento ).'</strong>
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
    foreach ($gare_xml->xpath('//lotto') as $lotto) {

        $tot_agg += (double)$lotto->importoAggiudicazione;
        $tot_liq += (double)$lotto->importoSommeLiquidate;
        $tot_lotti++;

        if ($a == '') {
            $a = ' class="alternate"';
        } else {
            $a = '';
        }

        if ( $lotto->tempiCompletamento->dataInizio ) {
            $dataInizio =  date("d/m/Y", strtotime($lotto->tempiCompletamento->dataInizio));
        } else { $dataInizio = 'N.D.'; }
        if ( $lotto->tempiCompletamento->dataUltimazione ) {
            $dataUltimazione =  date("d/m/Y", strtotime($lotto->tempiCompletamento->dataUltimazione));
        } else { $dataUltimazione = 'N.D.'; }

        echo '<tr' . $a . '>
            <td class="row-title"><label for="tablecell">' . esc_html( $lotto->cig ) . '</label></td>
            <td>' . esc_html( $lotto->oggetto ) . '</td>
            <td> ' . number_format((double)$lotto->importoAggiudicazione, 2, ',', '.') . '</td>
            <td> ' . number_format((double)$lotto->importoSommeLiquidate, 2, ',', '.') . '</td>
            <td>' . $dataInizio . '<br>' . $dataUltimazione . '</td>
            </tr><tr><td></td><td colspan="4"><small>' . esc_html( $lotto->sceltaContraente ) . '<br>Partecipanti:<br>';
        foreach ($lotto->partecipanti->partecipante as $partecipante) {
            echo esc_html( $partecipante->ragioneSociale ) . ' (' . esc_html( $partecipante->codiceFiscale ) . ')<br>';
        }
        echo 'Aggiudicatari:<br>';
        foreach ($lotto->aggiudicatari->aggiudicatario as $aggiudicatario) {
            echo esc_html( $aggiudicatario->ragioneSociale ) . ' (' .esc_html( $aggiudicatario->codiceFiscale ) . ')<br>';
        }
        echo '</small></td></tr>';
    }
    echo '<tfoot>
              <tr>
                <td>Totali</td>
                <td>Numero Lotti: <strong>'. number_format((double)$tot_lotti, 0, ',', '.') . '</strong></td>
                <td>'. number_format((double)$tot_agg, 2, ',', '.') . '</td>
                <td>'. number_format((double)$tot_liq, 2, ',', '.') . '</td>
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
add_action('template_redirect', 'anacxmlviewer_template');
function anacxmlviewer_template() {
    global $wp, $wp_query;
    if (isset($wp->query_vars['post_type']) && $wp->query_vars['post_type'] == 'anac-xml-view') {
        if (have_posts()) {
            add_filter('the_content', 'anacxmlviewer_template_filter');
        } else {
            $wp_query->is_404 = true;
        }
    }
}
function anacxmlviewer_template_filter($content) {
    global $wp_query;
    ob_start();
    anacxmlviewer_generatabella($wp_query->post->ID);
    $anacxmlshortcode = ob_get_clean();
    return $anacxmlshortcode;
}
?>
