<?php
/**
 * Frontend asset registration.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Assets
{
    private Atlas_Solar_Configurator_Session $session;
    private Atlas_Solar_Configurator_Settings $settings;

    public function __construct(
        Atlas_Solar_Configurator_Session $session,
        Atlas_Solar_Configurator_Settings $settings
    ) {
        $this->session = $session;
        $this->settings = $settings;
    }

    public function register(): void
    {
        wp_register_style(
            'asc-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            [],
            '1.9.4'
        );

        wp_register_script(
            'asc-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_register_style(
            'atlas-solar-configurator',
            ASC_PLUGIN_URL . 'public/css/configurator.css',
            ['asc-leaflet'],
            ASC_VERSION
        );

        wp_register_script(
            'asc-national-orthophoto',
            ASC_PLUGIN_URL . 'public/js/national-orthophoto.js',
            ['asc-leaflet'],
            ASC_VERSION,
            true
        );

        wp_localize_script(
            'asc-national-orthophoto',
            'ASC_IMAGERY_CONFIG',
            $this->settings->get_frontend_map_config()['aerial']
        );

        wp_register_script(
            'atlas-solar-configurator',
            ASC_PLUGIN_URL . 'public/js/configurator.js',
            ['asc-leaflet', 'asc-national-orthophoto'],
            ASC_VERSION,
            true
        );

        wp_localize_script(
            'atlas-solar-configurator',
            'ASC_CONFIG',
            [
                'version' => ASC_VERSION,
                'mockMode' => ASC_MOCK_MODE,
                'sessionId' => $this->session->get_session_id(),
                'storageKey' => 'atlas_solar_configurator_state',
                'map' => $this->settings->get_frontend_map_config(),
            ]
        );
    }

    public function enqueue(): void
    {
        wp_enqueue_style('atlas-solar-configurator');
        wp_enqueue_script('atlas-solar-configurator');
    }
}
