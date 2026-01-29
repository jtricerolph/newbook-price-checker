<?php
/**
 * NewBook API Wrapper
 *
 * Handles secure server-side communication with the NewBook API.
 * Credentials are never exposed to the frontend.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBPC_NewBook_API {

    /**
     * API endpoint
     */
    const API_URL = 'https://api.newbook.cloud/rest/';

    /**
     * Request action for availability pricing
     */
    const ACTION_AVAILABILITY = 'bookings_availability_pricing';

    /**
     * Region
     */
    const REGION = 'eu';

    /**
     * Site configuration
     */
    private $site;

    /**
     * Constructor
     */
    public function __construct($site) {
        $this->site = $site;
    }

    /**
     * Get availability pricing
     *
     * @param string $period_from Start date (DD-MM-YYYY)
     * @param string $period_to End date (DD-MM-YYYY)
     * @param int $adults Number of adults
     * @param int $children Number of children
     * @return array Combined pricing data
     */
    public function get_availability($period_from, $period_to, $adults, $children) {
        // Validate site configuration
        if (empty($this->site['api_key']) || empty($this->site['api_username']) || empty($this->site['api_password'])) {
            return array(
                'success' => false,
                'error' => 'Site not configured properly',
            );
        }

        // Get direct (online) rates
        $online_response = $this->call_api(array(
            'api_key' => $this->site['api_key'],
            'period_from' => $period_from,
            'period_to' => $period_to,
            'adults' => $adults,
            'children' => $children,
            'request_action' => self::ACTION_AVAILABILITY,
            'region' => self::REGION,
        ));

        // Get channel rates (with promo code)
        $promo_code = !empty($this->site['promo_code']) ? $this->site['promo_code'] : 'PriceCheckCode';
        $channel_response = $this->call_api(array(
            'api_key' => $this->site['api_key'],
            'period_from' => $period_from,
            'period_to' => $period_to,
            'adults' => $adults,
            'children' => $children,
            'promo_code' => $promo_code,
            'request_action' => self::ACTION_AVAILABILITY,
            'region' => self::REGION,
        ));

        // Parse and sanitize responses
        $online_data = $this->parse_response($online_response);
        $channel_data = $this->parse_response($channel_response);

        // Find cheapest rates
        $online_cheapest = $this->find_cheapest_rate($online_data);
        $channel_cheapest = $this->find_cheapest_rate($channel_data);

        return array(
            'success' => true,
            'site' => array(
                'code' => $this->site['code'],
                'name' => $this->site['name'],
                'booking_url' => $this->site['booking_url'],
            ),
            'online' => $online_cheapest,
            'channels' => $channel_cheapest,
        );
    }

    /**
     * Call the NewBook API
     */
    private function call_api($data) {
        $response = wp_remote_post(self::API_URL, array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->site['api_username'] . ':' . $this->site['api_password']),
            ),
            'body' => wp_json_encode($data),
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response',
            );
        }

        return $decoded;
    }

    /**
     * Parse API response
     */
    private function parse_response($response) {
        if (!is_array($response) || isset($response['error'])) {
            return null;
        }

        return $response;
    }

    /**
     * Find cheapest available rate from API response
     */
    private function find_cheapest_rate($data) {
        if (empty($data) || !isset($data['data'])) {
            return array(
                'available' => false,
                'cheapest_price' => 0,
                'room_type' => '',
                'tariff_name' => '',
                'message' => '',
            );
        }

        $cheapest_price = 0;
        $cheapest_room = '';
        $cheapest_tariff = '';
        $message = '';

        foreach ($data['data'] as $category) {
            if (empty($category['sites_available']) || $category['sites_available'] <= 0) {
                continue;
            }

            if (empty($category['tariffs_available'])) {
                continue;
            }

            foreach ($category['tariffs_available'] as $tariff) {
                if (empty($tariff['tariff_success']) || $tariff['tariff_success'] !== 'true') {
                    continue;
                }

                $price = floatval($tariff['tariff_total']);

                if ($price > 0 && ($cheapest_price === 0 || $price < $cheapest_price)) {
                    $cheapest_price = $price;
                    $cheapest_room = $category['category_name'] ?? '';
                    $cheapest_tariff = $tariff['tariff_label'] ?? '';
                    $message = $tariff['tariff_message'] ?? '';
                }
            }
        }

        return array(
            'available' => $cheapest_price > 0,
            'cheapest_price' => $cheapest_price,
            'room_type' => $cheapest_room,
            'tariff_name' => $cheapest_tariff,
            'message' => $message,
        );
    }

    /**
     * Build booking URL with parameters
     */
    public function build_booking_url($period_from, $period_to, $adults, $children) {
        $base_url = $this->site['booking_url'];

        if (empty($base_url)) {
            return '';
        }

        // Strip existing query string
        $url_parts = explode('?', $base_url);
        $base_url = $url_parts[0];

        // Build new URL with parameters
        $params = array(
            'available_from' => $period_from,
            'available_to' => $period_to,
            'adults' => $adults,
            'children' => $children,
        );

        return $base_url . '?' . http_build_query($params);
    }
}
