<?php
/**
 * Price Checker Widget Template
 *
 * @var array $data Template data from shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

$widget_id = 'nbpc-widget-' . uniqid();
?>

<div class="nbpc-widget" id="<?php echo esc_attr($widget_id); ?>" data-site="<?php echo esc_attr($data['site_code']); ?>">

    <?php if (!empty($data['title'])) : ?>
        <h3 class="nbpc-title"><?php echo esc_html($data['title']); ?></h3>
    <?php endif; ?>

    <!-- Form Section -->
    <div class="nbpc-form">
        <div class="nbpc-form-row">
            <div class="nbpc-field">
                <label for="<?php echo esc_attr($widget_id); ?>-arrive">
                    <?php esc_html_e('Arrival', 'newbook-price-checker'); ?>
                </label>
                <input type="date"
                       id="<?php echo esc_attr($widget_id); ?>-arrive"
                       class="nbpc-date-input nbpc-arrive"
                       min="<?php echo esc_attr(date('Y-m-d')); ?>"
                       required />
            </div>

            <div class="nbpc-field">
                <label for="<?php echo esc_attr($widget_id); ?>-depart">
                    <?php esc_html_e('Departure', 'newbook-price-checker'); ?>
                </label>
                <input type="date"
                       id="<?php echo esc_attr($widget_id); ?>-depart"
                       class="nbpc-date-input nbpc-depart"
                       min="<?php echo esc_attr(date('Y-m-d', strtotime('+1 day'))); ?>"
                       required />
            </div>
        </div>

        <div class="nbpc-form-row">
            <div class="nbpc-field">
                <label for="<?php echo esc_attr($widget_id); ?>-adults">
                    <?php esc_html_e('Adults', 'newbook-price-checker'); ?>
                </label>
                <select id="<?php echo esc_attr($widget_id); ?>-adults" class="nbpc-select nbpc-adults">
                    <?php for ($i = 1; $i <= $data['defaults']['max_adults']; $i++) : ?>
                        <option value="<?php echo esc_attr($i); ?>" <?php selected($i, $data['defaults']['adults']); ?>>
                            <?php echo esc_html($i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="nbpc-field">
                <label for="<?php echo esc_attr($widget_id); ?>-children">
                    <?php esc_html_e('Children', 'newbook-price-checker'); ?>
                </label>
                <select id="<?php echo esc_attr($widget_id); ?>-children" class="nbpc-select nbpc-children">
                    <?php for ($i = 0; $i <= $data['defaults']['max_children']; $i++) : ?>
                        <option value="<?php echo esc_attr($i); ?>" <?php selected($i, $data['defaults']['children']); ?>>
                            <?php echo esc_html($i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Intro Text (shown before search) -->
    <div class="nbpc-intro" style="display: none;">
        <p><?php esc_html_e('Select your dates to see our best rates.', 'newbook-price-checker'); ?></p>
    </div>

    <!-- Loading State -->
    <div class="nbpc-loading" style="display: none;">
        <div class="nbpc-spinner"></div>
        <p><?php esc_html_e('Checking availability...', 'newbook-price-checker'); ?></p>
    </div>

    <!-- Results Section -->
    <div class="nbpc-results" style="display: none;">
        <div class="nbpc-price-comparison">
            <div class="nbpc-price-box nbpc-channel-price">
                <span class="nbpc-price-label"><?php esc_html_e('Other Sites', 'newbook-price-checker'); ?></span>
                <span class="nbpc-price-value nbpc-channel-value"></span>
                <span class="nbpc-room-type nbpc-channel-room"></span>
            </div>

            <div class="nbpc-price-box nbpc-online-price nbpc-highlight">
                <span class="nbpc-price-label"><?php esc_html_e('Book Direct', 'newbook-price-checker'); ?></span>
                <span class="nbpc-price-value nbpc-online-value"></span>
                <span class="nbpc-room-type nbpc-online-room"></span>
                <span class="nbpc-savings"></span>
            </div>
        </div>

        <div class="nbpc-book-now-container">
            <a href="#" class="nbpc-book-now-button" target="_blank" rel="noopener">
                <?php esc_html_e('Book Now - Best Price Guaranteed', 'newbook-price-checker'); ?>
            </a>
        </div>
    </div>

    <!-- Unavailable State -->
    <div class="nbpc-unavailable" style="display: none;">
        <p class="nbpc-unavailable-message">
            <?php esc_html_e('Sorry, no availability for your selected dates.', 'newbook-price-checker'); ?>
        </p>
    </div>

    <!-- Fallback Properties Section -->
    <?php if ($data['show_fallback']) : ?>
    <div class="nbpc-fallback" style="display: none;">
        <h4 class="nbpc-fallback-title">
            <?php esc_html_e('Try Our Other Properties', 'newbook-price-checker'); ?>
        </h4>
        <div class="nbpc-fallback-list"></div>
    </div>
    <?php endif; ?>

    <!-- Error State -->
    <div class="nbpc-error" style="display: none;">
        <p class="nbpc-error-message"></p>
    </div>

    <!-- Hidden data for JS -->
    <input type="hidden" class="nbpc-booking-url-base" value="<?php echo esc_attr($data['booking_url']); ?>" />
    <input type="hidden" class="nbpc-show-fallback" value="<?php echo $data['show_fallback'] ? '1' : '0'; ?>" />
</div>
