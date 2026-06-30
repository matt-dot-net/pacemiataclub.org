<?php
/**
 * Plugin Name: Seasonal Home Slider
 * Description: Automatically swaps the Avada/Fusion home slider by season, with a manual override.
 * Version: 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SHS_OPTION_OVERRIDE', 'shs_season_override');

/**
 * Valid season/override values.
 */
function shs_get_valid_override_values() {
    return array('auto', 'spring', 'summer', 'fall', 'winter');
}

/**
 * Return current season using fixed meteorological seasons.
 */
function shs_get_current_season() {
    $month = (int) current_time('n');

    if (in_array($month, array(3, 4, 5), true)) {
        return 'spring';
    }

    if (in_array($month, array(6, 7, 8), true)) {
        return 'summer';
    }

    if (in_array($month, array(9, 10, 11), true)) {
        return 'fall';
    }

    return 'winter';
}

/**
 * Return the selected season, respecting the admin override.
 */
function shs_get_effective_season() {
    $override = get_option(SHS_OPTION_OVERRIDE, 'auto');

    if (in_array($override, array('spring', 'summer', 'fall', 'winter'), true)) {
        return $override;
    }

    return shs_get_current_season();
}

/**
 * Map seasons to Avada/Fusion Slider names.
 *
 * These names must exactly match the slider names in Avada.
 */
function shs_get_seasonal_slider_name() {
    $season = shs_get_effective_season();

    $slider_names = array(
        'spring' => 'home-spring',
        'summer' => 'home-summer',
        'fall'   => 'home-fall',
        'winter' => 'home-winter',
    );

    return isset($slider_names[$season]) ? $slider_names[$season] : 'home';
}

/**
 * Build a shortcode string from tag + attributes.
 */
function shs_build_shortcode($tag, $attrs) {
    $parts = array();

    if (is_array($attrs)) {
        foreach ($attrs as $key => $value) {
            if (is_numeric($key)) {
                $parts[] = esc_attr($value);
            } else {
                $parts[] = sanitize_key($key) . '="' . esc_attr($value) . '"';
            }
        }
    }

    return '[' . $tag . ' ' . implode(' ', $parts) . ']';
}

/**
 * Intercept Avada/Fusion Slider shortcodes before they render.
 *
 * This only changes sliders named exactly "home".
 */
function shs_intercept_avada_slider_shortcode($return, $tag, $attr, $m) {
    static $already_swapping = false;

    if ($already_swapping) {
        return $return;
    }

    $supported_tags = array(
        'fusion_slider',
        'fusionslider',
        'fusion_fusionslider',
    );

    if (!in_array($tag, $supported_tags, true)) {
        return $return;
    }

    if (!is_array($attr)) {
        return $return;
    }

    if (!isset($attr['name'])) {
        return $return;
    }

    if (trim($attr['name']) !== 'home') {
        return $return;
    }

    $attr['name'] = shs_get_seasonal_slider_name();

    $shortcode = shs_build_shortcode($tag, $attr);

    $already_swapping = true;
    $output = do_shortcode($shortcode);
    $already_swapping = false;

    return $output;
}

add_filter('pre_do_shortcode_tag', 'shs_intercept_avada_slider_shortcode', 10, 4);

/**
 * Register admin setting.
 */
function shs_register_settings() {
    register_setting(
        'shs_settings_group',
        SHS_OPTION_OVERRIDE,
        array(
            'type'              => 'string',
            'sanitize_callback' => 'shs_sanitize_override_value',
            'default'           => 'auto',
        )
    );
}

add_action('admin_init', 'shs_register_settings');

/**
 * Sanitize override setting.
 */
function shs_sanitize_override_value($value) {
    $value = is_string($value) ? strtolower(trim($value)) : 'auto';

    if (!in_array($value, shs_get_valid_override_values(), true)) {
        return 'auto';
    }

    return $value;
}

/**
 * Add settings page.
 */
function shs_add_settings_page() {
    add_options_page(
        'Seasonal Home Slider',
        'Seasonal Home Slider',
        'manage_options',
        'seasonal-home-slider',
        'shs_render_settings_page'
    );
}

add_action('admin_menu', 'shs_add_settings_page');

/**
 * Render settings page.
 */
function shs_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $override = get_option(SHS_OPTION_OVERRIDE, 'auto');
    $effective_season = shs_get_effective_season();
    $current_slider = shs_get_seasonal_slider_name();

    ?>
    <div class="wrap">
        <h1>Seasonal Home Slider</h1>

        <p>
            Choose <strong>Auto</strong> to use the current season automatically,
            or manually force a specific seasonal homepage slider.
        </p>

        <form method="post" action="options.php">
            <?php settings_fields('shs_settings_group'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(SHS_OPTION_OVERRIDE); ?>">Season Override</label>
                    </th>
                    <td>
                        <select
                            name="<?php echo esc_attr(SHS_OPTION_OVERRIDE); ?>"
                            id="<?php echo esc_attr(SHS_OPTION_OVERRIDE); ?>"
                        >
                            <option value="auto" <?php selected($override, 'auto'); ?>>Auto</option>
                            <option value="spring" <?php selected($override, 'spring'); ?>>Spring</option>
                            <option value="summer" <?php selected($override, 'summer'); ?>>Summer</option>
                            <option value="fall" <?php selected($override, 'fall'); ?>>Fall</option>
                            <option value="winter" <?php selected($override, 'winter'); ?>>Winter</option>
                        </select>

                        <p class="description">
                            Auto uses fixed seasons:
                            Spring = March–May,
                            Summer = June–August,
                            Fall = September–November,
                            Winter = December–February.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Seasonal Slider Settings'); ?>
        </form>

        <hr>

        <h2>Current Selection</h2>

        <p>
            <strong>Effective season:</strong>
            <?php echo esc_html(ucfirst($effective_season)); ?>
        </p>

        <p>
            <strong>Slider being used:</strong>
            <code><?php echo esc_html($current_slider); ?></code>
        </p>

        <p>
            The Avada Slider element on the homepage should remain set to:
            <code>home</code>
        </p>
    </div>
    <?php
}