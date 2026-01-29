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

    <!-- Form Section - All fields inline -->
    <div class="nbpc-form">
        <div class="nbpc-form-row nbpc-form-inline">
            <div class="nbpc-field nbpc-field-date">
                <label for="<?php echo esc_attr($widget_id); ?>-arrive">
                    <?php esc_html_e('Arrival', 'newbook-price-checker'); ?>
                </label>
                <input type="date"
                       id="<?php echo esc_attr($widget_id); ?>-arrive"
                       class="nbpc-date-input nbpc-arrive"
                       min="<?php echo esc_attr(date('Y-m-d')); ?>"
                       required />
            </div>

            <div class="nbpc-field nbpc-field-nights">
                <label for="<?php echo esc_attr($widget_id); ?>-nights">
                    <?php esc_html_e('Nights', 'newbook-price-checker'); ?>
                </label>
                <select id="<?php echo esc_attr($widget_id); ?>-nights" class="nbpc-select nbpc-nights">
                    <?php for ($i = 1; $i <= $data['defaults']['max_nights']; $i++) : ?>
                        <option value="<?php echo esc_attr($i); ?>" <?php selected($i, $data['defaults']['nights']); ?>>
                            <?php echo esc_html($i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="nbpc-field nbpc-field-guests">
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

            <div class="nbpc-field nbpc-field-guests">
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
            <!-- Other Sites (Channel) Price -->
            <div class="nbpc-price-box nbpc-channel-price">
                <?php if (!empty($data['channel_image'])) : ?>
                    <img src="<?php echo esc_url($data['channel_image']); ?>" alt="<?php esc_attr_e('Other Sites', 'newbook-price-checker'); ?>" class="nbpc-price-image" />
                <?php endif; ?>
                <div class="nbpc-price-text">
                    <span class="nbpc-price-label"><?php esc_html_e('Other Sites', 'newbook-price-checker'); ?></span>
                    <span class="nbpc-price-value nbpc-channel-value"></span>
                    <span class="nbpc-room-type nbpc-channel-room"></span>
                </div>
            </div>

            <!-- Direct Price (clickable link) -->
            <a href="#" class="nbpc-price-box nbpc-online-price nbpc-highlight nbpc-price-link" target="_blank" rel="noopener">
                <?php if (!empty($data['direct_image'])) : ?>
                    <img src="<?php echo esc_url($data['direct_image']); ?>" alt="<?php esc_attr_e('Book Direct', 'newbook-price-checker'); ?>" class="nbpc-price-image" />
                <?php endif; ?>
                <div class="nbpc-price-text">
                    <span class="nbpc-price-label"><?php esc_html_e('Book Direct', 'newbook-price-checker'); ?></span>
                    <span class="nbpc-price-value nbpc-online-value"></span>
                    <span class="nbpc-room-type nbpc-online-room"></span>
                    <span class="nbpc-savings"></span>
                </div>
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
