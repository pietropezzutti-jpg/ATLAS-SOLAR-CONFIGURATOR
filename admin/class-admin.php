<?php
/**
 * Admin menu and settings page.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Admin
{
    private Atlas_Solar_Configurator_Settings $settings;

    public function __construct(Atlas_Solar_Configurator_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register_menu(): void
    {
        add_menu_page(
            'ATLAS Configurator',
            'ATLAS Configurator',
            'manage_options',
            'atlas-solar-configurator',
            [$this, 'render_settings_page'],
            'dashicons-admin-site-alt3',
            56
        );
    }

    public function render_settings_page(): void
    {
        $summary = $this->settings->get_summary();
        $map_options = $this->settings->get_map_options();

        include ASC_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
}
