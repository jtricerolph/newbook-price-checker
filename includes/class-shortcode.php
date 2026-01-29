<?php
/**
 * Shortcode Handler
 *
 * Registers and renders the [newbook_price_checker] shortcode.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBPC_Shortcode {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('newbook_price_checker', array($this, 'render_shortcode'));
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts) {
        $plugin = NewBook_Price_Checker::get_instance();

        // Parse attributes
        $atts = shortcode_atts(array(
            'site' => '',
            'show_fallback' => 'true',
            'booking_url' => '',
            'title' => '',
        ), $atts, 'newbook_price_checker');

        // Get site configuration
        if (!empty($atts['site'])) {
            $site = $plugin->get_site($atts['site']);
        } else {
            $site = $plugin->get_primary_site();
        }

        if (!$site) {
            return '<p class="nbpc-error">' . esc_html__('Price checker not configured. Please configure a site in Settings > NewBook Price Checker.', 'newbook-price-checker') . '</p>';
        }

        // Prepare data for template
        $data = array(
            'site_code' => $site['code'],
            'site_name' => $site['name'],
            'booking_url' => !empty($atts['booking_url']) ? $atts['booking_url'] : $site['booking_url'],
            'show_fallback' => $atts['show_fallback'] !== 'false' && $plugin->get_option('enable_fallback', false),
            'title' => $atts['title'],
            'currency' => $plugin->get_currency_symbol(),
            'defaults' => array(
                'adults' => $plugin->get_option('default_adults', 2),
                'children' => $plugin->get_option('default_children', 0),
                'max_adults' => $plugin->get_option('max_adults', 6),
                'max_children' => $plugin->get_option('max_children', 4),
            ),
        );

        // Buffer output
        ob_start();
        include NBPC_PLUGIN_DIR . 'templates/widget.php';
        return ob_get_clean();
    }
}
