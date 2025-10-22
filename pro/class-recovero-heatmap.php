<?php
if (!defined('ABSPATH')) exit;

class Recovero_Heatmap {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_heatmap_page']);
        add_action('wp_ajax_recovero_heatmap_data', [$this, 'ajax_heatmap_data']);
    }

    public function add_heatmap_page() {
        add_submenu_page('recovero', 'Heatmap', 'Heatmap', 'manage_woocommerce', 'recovero-heatmap', [$this, 'page_heatmap']);
    }

    public function page_heatmap() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Recovero Location Heatmap', 'recovero'); ?></h1>
            <p><?php esc_html_e('This page shows aggregated cart locations. For better map visuals include a mapping library (Leaflet/Google Maps).', 'recovero'); ?></p>
            <div id="recovero-heatmap" style="height:500px;"></div>
            <script>
            (function($){
                $(function(){
                    $.get(ajaxurl, {action: 'recovero_heatmap_data'}, function(resp){
                        // resp should be list of {lat, lon, count}
                        console.log(resp);
                        // You can render heatmap with Leaflet. For now show JSON.
                        $('#recovero-heatmap').text(JSON.stringify(resp));
                    });
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }

    public function ajax_heatmap_data() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('no_permission');
        global $wpdb;
        $geo = $wpdb->prefix . 'recovero_geo_data';
        $rows = $wpdb->get_results("SELECT lat, lon, COUNT(*) as cnt FROM {$geo} WHERE lat IS NOT NULL AND lon IS NOT NULL GROUP BY lat, lon");
        wp_send_json($rows);
    }
}
