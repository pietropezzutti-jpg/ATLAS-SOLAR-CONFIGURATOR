<?php
/**
 * Plugin Name: ATLAS Solar Lead Configurator
 * Plugin URI: https://github.com/pietropezzutti-jpg/ATLAS-SOLAR-CONFIGURATOR
 * Description: Public WordPress multi-step solar lead configurator funnel for ATLAS.
 * Version: 0.5.1
 * Author: ATLAS
 * Text Domain: atlas-solar-configurator
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ASC_VERSION', '0.5.1');
define('ASC_PLUGIN_FILE', __FILE__);
define('ASC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASC_MOCK_MODE', true);

require_once ASC_PLUGIN_DIR . 'includes/class-session.php';
require_once ASC_PLUGIN_DIR . 'includes/class-settings.php';
require_once ASC_PLUGIN_DIR . 'includes/class-geocoder.php';
require_once ASC_PLUGIN_DIR . 'includes/class-geoapify-geocoder.php';
require_once ASC_PLUGIN_DIR . 'includes/class-atlas-boundary.php';
require_once ASC_PLUGIN_DIR . 'includes/class-atlas-transport.php';
require_once ASC_PLUGIN_DIR . 'includes/class-assets.php';
require_once ASC_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once ASC_PLUGIN_DIR . 'includes/class-plugin.php';

if (is_admin()) {
    require_once ASC_PLUGIN_DIR . 'admin/class-admin.php';
}

add_action(
    'plugins_loaded',
    static function () {
        Atlas_Solar_Configurator_Plugin::instance()->boot();
    }
);
