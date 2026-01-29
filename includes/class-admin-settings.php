<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class NBPC_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __('NewBook Price Checker', 'newbook-price-checker'),
            __('NewBook Price Checker', 'newbook-price-checker'),
            'manage_options',
            'nbpc-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('nbpc_settings_group', 'nbpc_settings', array($this, 'sanitize_settings'));

        // Display Settings Section
        add_settings_section(
            'nbpc_display_section',
            __('Display Settings', 'newbook-price-checker'),
            array($this, 'render_display_section'),
            'nbpc-settings'
        );

        add_settings_field(
            'currency_symbol',
            __('Currency Symbol', 'newbook-price-checker'),
            array($this, 'render_text_field'),
            'nbpc-settings',
            'nbpc_display_section',
            array('field' => 'currency_symbol', 'default' => '£')
        );

        add_settings_field(
            'default_adults',
            __('Default Adults', 'newbook-price-checker'),
            array($this, 'render_number_field'),
            'nbpc-settings',
            'nbpc_display_section',
            array('field' => 'default_adults', 'default' => 2, 'min' => 1, 'max' => 10)
        );

        add_settings_field(
            'default_children',
            __('Default Children', 'newbook-price-checker'),
            array($this, 'render_number_field'),
            'nbpc-settings',
            'nbpc_display_section',
            array('field' => 'default_children', 'default' => 0, 'min' => 0, 'max' => 10)
        );

        add_settings_field(
            'max_adults',
            __('Max Adults Dropdown', 'newbook-price-checker'),
            array($this, 'render_number_field'),
            'nbpc-settings',
            'nbpc_display_section',
            array('field' => 'max_adults', 'default' => 6, 'min' => 1, 'max' => 20)
        );

        add_settings_field(
            'max_children',
            __('Max Children Dropdown', 'newbook-price-checker'),
            array($this, 'render_number_field'),
            'nbpc-settings',
            'nbpc_display_section',
            array('field' => 'max_children', 'default' => 4, 'min' => 0, 'max' => 10)
        );

        // Fallback Section
        add_settings_section(
            'nbpc_fallback_section',
            __('Fallback Settings', 'newbook-price-checker'),
            array($this, 'render_fallback_section'),
            'nbpc-settings'
        );

        add_settings_field(
            'enable_fallback',
            __('Enable Fallback', 'newbook-price-checker'),
            array($this, 'render_checkbox_field'),
            'nbpc-settings',
            'nbpc_fallback_section',
            array('field' => 'enable_fallback', 'description' => 'Show alternative properties when primary is unavailable')
        );

        // Sites Section
        add_settings_section(
            'nbpc_sites_section',
            __('Sites Configuration', 'newbook-price-checker'),
            array($this, 'render_sites_section'),
            'nbpc-settings'
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('settings_page_nbpc-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'nbpc-admin-style',
            NBPC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            NBPC_VERSION
        );

        wp_enqueue_script(
            'nbpc-admin-script',
            NBPC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            NBPC_VERSION,
            true
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show save message
        if (isset($_GET['settings-updated'])) {
            add_settings_error('nbpc_messages', 'nbpc_message', __('Settings saved.', 'newbook-price-checker'), 'updated');
        }

        settings_errors('nbpc_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('nbpc_settings_group');
                do_settings_sections('nbpc-settings');
                submit_button(__('Save Settings', 'newbook-price-checker'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Section renderers
     */
    public function render_display_section() {
        echo '<p>' . esc_html__('Configure how the price checker displays.', 'newbook-price-checker') . '</p>';
    }

    public function render_fallback_section() {
        echo '<p>' . esc_html__('Configure fallback behavior when primary property is unavailable.', 'newbook-price-checker') . '</p>';
    }

    public function render_sites_section() {
        $options = get_option('nbpc_settings', array());
        $sites = isset($options['sites']) ? $options['sites'] : array();
        ?>
        <p><?php esc_html_e('Configure your NewBook properties. API credentials are stored securely and never exposed to the frontend.', 'newbook-price-checker'); ?></p>

        <div id="nbpc-sites-container">
            <?php
            if (!empty($sites)) {
                foreach ($sites as $index => $site) {
                    $this->render_site_row($index, $site);
                }
            }
            ?>
        </div>

        <button type="button" id="nbpc-add-site" class="button button-secondary">
            <?php esc_html_e('+ Add Site', 'newbook-price-checker'); ?>
        </button>

        <!-- Template for new site row -->
        <script type="text/template" id="nbpc-site-template">
            <?php $this->render_site_row('{{INDEX}}', array()); ?>
        </script>
        <?php
    }

    /**
     * Render a single site configuration row
     */
    private function render_site_row($index, $site) {
        $defaults = array(
            'code' => '',
            'name' => '',
            'api_username' => '',
            'api_password' => '',
            'api_key' => '',
            'booking_url' => '',
            'promo_code' => 'PriceCheckCode',
            'is_primary' => false,
            'show_as_fallback' => false,
        );
        $site = wp_parse_args($site, $defaults);
        $prefix = "nbpc_settings[sites][$index]";
        ?>
        <div class="nbpc-site-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="nbpc-site-header">
                <span class="nbpc-site-title">
                    <?php echo $site['name'] ? esc_html($site['name']) : esc_html__('New Site', 'newbook-price-checker'); ?>
                </span>
                <button type="button" class="nbpc-toggle-site button button-small">
                    <?php esc_html_e('Expand', 'newbook-price-checker'); ?>
                </button>
                <button type="button" class="nbpc-remove-site button button-small button-link-delete">
                    <?php esc_html_e('Remove', 'newbook-price-checker'); ?>
                </button>
            </div>
            <div class="nbpc-site-fields" style="display: none;">
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Site Code', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($prefix); ?>[code]"
                                   value="<?php echo esc_attr($site['code']); ?>"
                                   placeholder="e.g., NO4" class="regular-text" required />
                            <p class="description"><?php esc_html_e('Unique identifier for this site', 'newbook-price-checker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Display Name', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($prefix); ?>[name]"
                                   value="<?php echo esc_attr($site['name']); ?>"
                                   placeholder="e.g., Number Four at Stow" class="regular-text nbpc-site-name-input" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('API Username', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($prefix); ?>[api_username]"
                                   value="<?php echo esc_attr($site['api_username']); ?>"
                                   class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('API Password', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr($prefix); ?>[api_password]"
                                   value="<?php echo esc_attr($site['api_password']); ?>"
                                   class="regular-text" autocomplete="new-password" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('API Key', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($prefix); ?>[api_key]"
                                   value="<?php echo esc_attr($site['api_key']); ?>"
                                   placeholder="instances_xxx" class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Booking URL', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="url" name="<?php echo esc_attr($prefix); ?>[booking_url]"
                                   value="<?php echo esc_attr($site['booking_url']); ?>"
                                   placeholder="https://booking.newbook.cloud/..." class="large-text" />
                            <p class="description"><?php esc_html_e('URL for the "Book Now" button', 'newbook-price-checker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Channel Promo Code', 'newbook-price-checker'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr($prefix); ?>[promo_code]"
                                   value="<?php echo esc_attr($site['promo_code']); ?>"
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Promo code used to fetch channel/OTA rates', 'newbook-price-checker'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Options', 'newbook-price-checker'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[is_primary]"
                                       value="1" <?php checked($site['is_primary']); ?> class="nbpc-primary-checkbox" />
                                <?php esc_html_e('Primary site (default for shortcode)', 'newbook-price-checker'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($prefix); ?>[show_as_fallback]"
                                       value="1" <?php checked($site['show_as_fallback']); ?> />
                                <?php esc_html_e('Show as fallback option', 'newbook-price-checker'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Field renderers
     */
    public function render_text_field($args) {
        $options = get_option('nbpc_settings', array());
        $value = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        ?>
        <input type="text" name="nbpc_settings[<?php echo esc_attr($args['field']); ?>]"
               value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }

    public function render_number_field($args) {
        $options = get_option('nbpc_settings', array());
        $value = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        ?>
        <input type="number" name="nbpc_settings[<?php echo esc_attr($args['field']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>"
               max="<?php echo esc_attr($args['max']); ?>"
               class="small-text" />
        <?php
    }

    public function render_checkbox_field($args) {
        $options = get_option('nbpc_settings', array());
        $checked = isset($options[$args['field']]) ? $options[$args['field']] : false;
        ?>
        <label>
            <input type="checkbox" name="nbpc_settings[<?php echo esc_attr($args['field']); ?>]"
                   value="1" <?php checked($checked); ?> />
            <?php if (isset($args['description'])) echo esc_html($args['description']); ?>
        </label>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Display settings
        $sanitized['currency_symbol'] = sanitize_text_field($input['currency_symbol'] ?? '£');
        $sanitized['default_adults'] = absint($input['default_adults'] ?? 2);
        $sanitized['default_children'] = absint($input['default_children'] ?? 0);
        $sanitized['max_adults'] = absint($input['max_adults'] ?? 6);
        $sanitized['max_children'] = absint($input['max_children'] ?? 4);

        // Fallback
        $sanitized['enable_fallback'] = !empty($input['enable_fallback']);

        // Sites
        $sanitized['sites'] = array();
        if (!empty($input['sites']) && is_array($input['sites'])) {
            foreach ($input['sites'] as $site) {
                if (empty($site['code'])) {
                    continue; // Skip sites without code
                }

                $sanitized['sites'][] = array(
                    'code' => sanitize_text_field($site['code']),
                    'name' => sanitize_text_field($site['name'] ?? ''),
                    'api_username' => sanitize_text_field($site['api_username'] ?? ''),
                    'api_password' => $site['api_password'] ?? '', // Don't sanitize passwords
                    'api_key' => sanitize_text_field($site['api_key'] ?? ''),
                    'booking_url' => esc_url_raw($site['booking_url'] ?? ''),
                    'promo_code' => sanitize_text_field($site['promo_code'] ?? 'PriceCheckCode'),
                    'is_primary' => !empty($site['is_primary']),
                    'show_as_fallback' => !empty($site['show_as_fallback']),
                );
            }

            // Ensure only one primary
            $has_primary = false;
            foreach ($sanitized['sites'] as &$site) {
                if ($site['is_primary']) {
                    if ($has_primary) {
                        $site['is_primary'] = false;
                    }
                    $has_primary = true;
                }
            }
        }

        return $sanitized;
    }
}
