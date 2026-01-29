<?php
/**
 * AJAX Handler
 *
 * Handles AJAX requests for price checking.
 * Acts as a secure proxy - API credentials never reach the browser.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBPC_Ajax_Handler {

    private static $instance = null;

    /**
     * Rate limit: max requests per window
     */
    const RATE_LIMIT = 15;

    /**
     * Rate limit window in seconds
     */
    const RATE_WINDOW = 60;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Price check endpoint - available to both logged in and non-logged in users
        add_action('wp_ajax_nbpc_price_check', array($this, 'handle_price_check'));
        add_action('wp_ajax_nopriv_nbpc_price_check', array($this, 'handle_price_check'));

        // Fallback check endpoint
        add_action('wp_ajax_nbpc_fallback_check', array($this, 'handle_fallback_check'));
        add_action('wp_ajax_nopriv_nbpc_fallback_check', array($this, 'handle_fallback_check'));
    }

    /**
     * Handle price check request
     */
    public function handle_price_check() {
        // Check rate limit
        if ($this->is_rate_limited()) {
            wp_send_json_error(array('message' => 'Too many requests. Please wait a moment.'));
            return;
        }

        // Verify nonce
        if (!check_ajax_referer('nbpc_price_check', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        // Get and validate parameters
        $site_code = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $available_from = isset($_POST['available_from']) ? sanitize_text_field($_POST['available_from']) : '';
        $available_to = isset($_POST['available_to']) ? sanitize_text_field($_POST['available_to']) : '';
        $adults = isset($_POST['adults']) ? absint($_POST['adults']) : 2;
        $children = isset($_POST['children']) ? absint($_POST['children']) : 0;

        // Validate required fields
        if (empty($available_from) || empty($available_to)) {
            wp_send_json_error(array('message' => 'Please select arrival and departure dates'));
            return;
        }

        // Validate date format (DD-MM-YYYY)
        if (!$this->validate_date($available_from) || !$this->validate_date($available_to)) {
            wp_send_json_error(array('message' => 'Invalid date format'));
            return;
        }

        // Get site configuration
        $plugin = NewBook_Price_Checker::get_instance();

        if (!empty($site_code)) {
            $site = $plugin->get_site($site_code);
        } else {
            $site = $plugin->get_primary_site();
        }

        if (!$site) {
            wp_send_json_error(array('message' => 'Site not configured'));
            return;
        }

        // Call the API
        $api = new NBPC_NewBook_API($site);
        $result = $api->get_availability($available_from, $available_to, $adults, $children);

        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['error'] ?? 'API request failed'));
            return;
        }

        // Add booking URL
        $result['booking_url'] = $api->build_booking_url($available_from, $available_to, $adults, $children);

        wp_send_json_success($result);
    }

    /**
     * Handle fallback sites check
     */
    public function handle_fallback_check() {
        // Check rate limit
        if ($this->is_rate_limited()) {
            wp_send_json_error(array('message' => 'Too many requests. Please wait a moment.'));
            return;
        }

        // Verify nonce
        if (!check_ajax_referer('nbpc_price_check', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        // Get parameters
        $exclude_site = isset($_POST['exclude_site']) ? sanitize_text_field($_POST['exclude_site']) : '';
        $available_from = isset($_POST['available_from']) ? sanitize_text_field($_POST['available_from']) : '';
        $available_to = isset($_POST['available_to']) ? sanitize_text_field($_POST['available_to']) : '';
        $adults = isset($_POST['adults']) ? absint($_POST['adults']) : 2;
        $children = isset($_POST['children']) ? absint($_POST['children']) : 0;

        // Validate dates
        if (empty($available_from) || empty($available_to)) {
            wp_send_json_error(array('message' => 'Dates required'));
            return;
        }

        $plugin = NewBook_Price_Checker::get_instance();
        $fallback_sites = $plugin->get_fallback_sites($exclude_site);

        if (empty($fallback_sites)) {
            wp_send_json_success(array('sites' => array()));
            return;
        }

        $results = array();

        foreach ($fallback_sites as $site) {
            $api = new NBPC_NewBook_API($site);
            $result = $api->get_availability($available_from, $available_to, $adults, $children);

            if ($result['success'] && $result['online']['available']) {
                $results[] = array(
                    'code' => $site['code'],
                    'name' => $site['name'],
                    'cheapest_price' => $result['online']['cheapest_price'],
                    'booking_url' => $api->build_booking_url($available_from, $available_to, $adults, $children),
                );
            }
        }

        wp_send_json_success(array('sites' => $results));
    }

    /**
     * Validate date format (DD-MM-YYYY)
     */
    private function validate_date($date) {
        // Accept both DD-MM-YYYY and YYYY-MM-DD (HTML5 date input format)
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            return true;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return true;
        }
        return false;
    }

    /**
     * Check if current request is rate limited
     *
     * @return bool True if rate limited, false if allowed
     */
    private function is_rate_limited() {
        $ip = $this->get_client_ip();
        $key = 'nbpc_rate_' . md5($ip);

        $count = get_transient($key);

        if ($count === false) {
            // First request in window
            set_transient($key, 1, self::RATE_WINDOW);
            return false;
        }

        if ($count >= self::RATE_LIMIT) {
            return true;
        }

        // Increment counter
        set_transient($key, $count + 1, self::RATE_WINDOW);
        return false;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can contain multiple IPs, get the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }
}
