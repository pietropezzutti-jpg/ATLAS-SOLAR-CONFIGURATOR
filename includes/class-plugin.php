<?php
/**
 * Plugin composition root.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Plugin
{
    private static ?self $instance = null;

    private Atlas_Solar_Configurator_Assets $assets;
    private Atlas_Solar_Configurator_Shortcode $shortcode;
    private Atlas_Solar_Configurator_Settings $settings;

    private function __construct()
    {
        $session = new Atlas_Solar_Configurator_Session();
        $this->assets = new Atlas_Solar_Configurator_Assets($session);
        $this->shortcode = new Atlas_Solar_Configurator_Shortcode($this->assets, $session);
        $this->settings = new Atlas_Solar_Configurator_Settings();
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        add_action('wp_enqueue_scripts', [$this->assets, 'register']);
        add_shortcode('atlas_solar_configurator', [$this->shortcode, 'render']);

        if (is_admin()) {
            $admin = new Atlas_Solar_Configurator_Admin($this->settings);
            add_action('admin_menu', [$admin, 'register_menu']);
        }
    }
}
