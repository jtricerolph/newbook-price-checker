<?php
/**
 * Plugin Name: NewBook Price Checker
 * Plugin URI: https://github.com/JTR-Solutions
 * Description: Price comparison widget for NewBook accommodation bookings. Shows direct vs OTA rates to encourage direct bookings.
 * Version: 1.0.0
 * Author: JTR Solutions
 * Author URI: https://tricerolph.com
 * License: GPL2
 * Text Domain: newbook-price-checker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('NBPC_VERSION', '1.0.0');
define('NBPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NBPC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NBPC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class NewBook_Price_Checker {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once NBPC_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once NBPC_PLUGIN_DIR . 'includes/class-newbook-api.php';
        require_once NBPC_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once NBPC_PLUGIN_DIR . 'includes/class-shortcode.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components
        add_action('init', array($this, 'init'));

        // Admin hooks
        if (is_admin()) {
            NBPC_Admin_Settings::get_instance();
        }

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Initialize AJAX handler
        NBPC_Ajax_Handler::get_instance();

        // Initialize shortcode
        NBPC_Shortcode::get_instance();
    }

    /**
     * Plugin initialization
     */
    public function init() {
        load_plugin_textdomain('newbook-price-checker', false, dirname(NBPC_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'newbook_price_checker')) {
            wp_enqueue_style(
                'nbpc-style',
                NBPC_PLUGIN_URL . 'assets/css/price-checker.css',
                array(),
                NBPC_VERSION
            );

            wp_enqueue_script(
                'nbpc-script',
                NBPC_PLUGIN_URL . 'assets/js/price-checker.js',
                array(),
                NBPC_VERSION,
                true
            );

            // Pass data to JavaScript
            wp_localize_script('nbpc-script', 'nbpcData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nbpc_price_check'),
                'currency' => $this->get_currency_symbol(),
                'defaults' => array(
                    'adults' => $this->get_option('default_adults', 2),
                    'children' => $this->get_option('default_children', 0),
                    'maxAdults' => $this->get_option('max_adults', 6),
                    'maxChildren' => $this->get_option('max_children', 4),
                ),
            ));
        }
    }

    /**
     * Get plugin option
     */
    public function get_option($key, $default = '') {
        $options = get_option('nbpc_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Get currency symbol
     */
    public function get_currency_symbol() {
        return $this->get_option('currency_symbol', '£');
    }

    /**
     * Get configured sites
     */
    public function get_sites() {
        return $this->get_option('sites', array());
    }

    /**
     * Get site by code
     */
    public function get_site($site_code) {
        $sites = $this->get_sites();
        foreach ($sites as $site) {
            if ($site['code'] === $site_code) {
                return $site;
            }
        }
        return null;
    }

    /**
     * Get primary site
     */
    public function get_primary_site() {
        $sites = $this->get_sites();
        foreach ($sites as $site) {
            if (!empty($site['is_primary'])) {
                return $site;
            }
        }
        // Return first site if no primary set
        return !empty($sites) ? $sites[0] : null;
    }

    /**
     * Get fallback sites
     */
    public function get_fallback_sites($exclude_code = '') {
        if (!$this->get_option('enable_fallback', false)) {
            return array();
        }

        $sites = $this->get_sites();
        $fallback = array();

        foreach ($sites as $site) {
            if ($site['code'] !== $exclude_code && !empty($site['show_as_fallback'])) {
                $fallback[] = $site;
            }
        }

        return $fallback;
    }
}

/**
 * Plugin activation
 */
function nbpc_activate() {
    // Set default options if not exist
    if (!get_option('nbpc_settings')) {
        $defaults = array(
            'currency_symbol' => '£',
            'default_adults' => 2,
            'default_children' => 0,
            'max_adults' => 6,
            'max_children' => 4,
            'enable_fallback' => false,
            'sites' => array(),
        );
        add_option('nbpc_settings', $defaults);
    }
}
register_activation_hook(__FILE__, 'nbpc_activate');

/**
 * Plugin deactivation
 */
function nbpc_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'nbpc_deactivate');

/**
 * Initialize the plugin
 */
function nbpc_init() {
    return NewBook_Price_Checker::get_instance();
}
add_action('plugins_loaded', 'nbpc_init');
