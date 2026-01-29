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

        // Get all rates grouped by room type for expanded view
        $all_rates = $this->get_all_rates_grouped($online_data);

        return array(
            'success' => true,
            'site' => array(
                'code' => $this->site['code'],
                'name' => $this->site['name'],
                'booking_url' => $this->site['booking_url'],
            ),
            'online' => $online_cheapest,
            'channels' => $channel_cheapest,
            'all_rates' => $all_rates,
        );
    }

    /**
     * Get all available rates grouped by room type
     */
    private function get_all_rates_grouped($data) {
        $grouped = array();

        if (empty($data) || !isset($data['data'])) {
            return $grouped;
        }

        foreach ($data['data'] as $category) {
            $category_name = $category['category_name'] ?? 'Unknown';
            $sites_available = isset($category['sites_available']) ? intval($category['sites_available']) : 0;

            if ($sites_available <= 0 || empty($category['tariffs_available'])) {
                continue;
            }

            $rates = array();
            foreach ($category['tariffs_available'] as $tariff) {
                $tariff_success = $tariff['tariff_success'] ?? '';
                if ($tariff_success !== 'true') {
                    continue;
                }

                $price = floatval($tariff['tariff_total'] ?? 0);
                if ($price <= 0) {
                    continue;
                }

                // Use tariff_short_description for inclusions (e.g. "includes accommodation and full English breakfast")
                $inclusions = $tariff['tariff_short_description'] ?? '';

                // Use tariff_message for description/status
                $description = $tariff['tariff_message'] ?? '';

                $rates[] = array(
                    'tariff_name' => $tariff['tariff_label'] ?? '',
                    'price' => $price,
                    'description' => $description,
                    'inclusions' => $inclusions,
                );
            }

            if (!empty($rates)) {
                $grouped[] = array(
                    'room_type' => $category_name,
                    'available' => $sites_available,
                    'rates' => $rates,
                );
            }
        }

        return $grouped;
    }

    /**
     * Flatten any data type to a readable string
     * Handles strings, arrays, objects, and nested structures
     * Skips date-like values
     */
    private function flatten_to_string($data) {
        if (empty($data)) {
            return '';
        }

        if (is_string($data)) {
            // Skip if it looks like a date
            if ($this->looks_like_date($data)) {
                return '';
            }
            return $data;
        }

        if (is_array($data)) {
            $strings = array();
            foreach ($data as $key => $value) {
                // Skip keys that are date-related
                if (is_string($key) && preg_match('/date|period|from|to|start|end/i', $key)) {
                    continue;
                }

                if (is_string($value) && !$this->looks_like_date($value)) {
                    $strings[] = $value;
                } elseif (is_array($value) || is_object($value)) {
                    // Try to extract inclusions-specific fields first
                    $value = (array) $value;
                    $extracted = null;

                    // Look for inclusion-specific fields
                    foreach (array('inclusion', 'inclusions', 'include', 'includes', 'feature', 'amenity') as $field) {
                        if (isset($value[$field]) && is_string($value[$field])) {
                            $extracted = $value[$field];
                            break;
                        }
                    }

                    // Fall back to common text fields
                    if (!$extracted) {
                        foreach (array('name', 'label', 'text', 'description', 'title') as $field) {
                            if (isset($value[$field]) && is_string($value[$field]) && !$this->looks_like_date($value[$field])) {
                                $extracted = $value[$field];
                                break;
                            }
                        }
                    }

                    if ($extracted) {
                        $strings[] = $extracted;
                    }
                }
            }
            return implode(', ', array_filter($strings));
        }

        if (is_object($data)) {
            return $this->flatten_to_string((array) $data);
        }

        return (string) $data;
    }

    /**
     * Check if a string looks like a date
     */
    private function looks_like_date($str) {
        if (!is_string($str)) {
            return false;
        }
        // Check for day names
        if (preg_match('/\b(Mon|Tue|Wed|Thu|Fri|Sat|Sun|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i', $str)) {
            return true;
        }
        // Check for month names with numbers
        if (preg_match('/\b(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d/i', $str)) {
            return true;
        }
        // Check for date patterns like DD-MM-YYYY or YYYY-MM-DD
        if (preg_match('/\d{2,4}[-\/]\d{2}[-\/]\d{2,4}/', $str)) {
            return true;
        }
        return false;
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
                'min_nights' => 0,
                'debug_raw' => $data,
            );
        }

        $cheapest_price = 0;
        $cheapest_room = '';
        $cheapest_tariff = '';
        $message = '';
        $min_nights = 0;
        $all_tariff_messages = array();

        foreach ($data['data'] as $category) {
            $category_name = $category['category_name'] ?? 'Unknown';

            if (empty($category['tariffs_available'])) {
                continue;
            }

            foreach ($category['tariffs_available'] as $tariff) {
                $tariff_label = $tariff['tariff_label'] ?? '';
                $tariff_message = $tariff['tariff_message'] ?? '';
                $tariff_success = $tariff['tariff_success'] ?? '';
                $tariff_min_nights = isset($tariff['tariff_min_nights']) ? intval($tariff['tariff_min_nights']) : 0;

                // Collect all messages for debugging
                if (!empty($tariff_message)) {
                    $all_tariff_messages[] = array(
                        'category' => $category_name,
                        'tariff' => $tariff_label,
                        'success' => $tariff_success,
                        'message' => $tariff_message,
                        'min_nights' => $tariff_min_nights,
                    );
                }

                // Track minimum nights from failed tariffs
                if ($tariff_success !== 'true' && $tariff_min_nights > 0) {
                    if ($min_nights === 0 || $tariff_min_nights < $min_nights) {
                        $min_nights = $tariff_min_nights;
                    }
                }

                // Check for minimum nights in message (fallback)
                if ($tariff_success !== 'true' && preg_match('/(\d+)\s*night\s*minimum/i', $tariff_message, $matches)) {
                    $msg_min = intval($matches[1]);
                    if ($min_nights === 0 || $msg_min < $min_nights) {
                        $min_nights = $msg_min;
                    }
                }

                // Skip unavailable
                if (empty($category['sites_available']) || $category['sites_available'] <= 0) {
                    continue;
                }

                if ($tariff_success !== 'true') {
                    continue;
                }

                $price = floatval($tariff['tariff_total']);

                if ($price > 0 && ($cheapest_price === 0 || $price < $cheapest_price)) {
                    $cheapest_price = $price;
                    $cheapest_room = $category_name;
                    $cheapest_tariff = $tariff_label;
                    $message = $tariff_message;
                }
            }
        }

        return array(
            'available' => $cheapest_price > 0,
            'cheapest_price' => $cheapest_price,
            'room_type' => $cheapest_room,
            'tariff_name' => $cheapest_tariff,
            'message' => $message,
            'min_nights' => $min_nights,
            'debug_messages' => $all_tariff_messages,
            'debug_raw' => $data,
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
