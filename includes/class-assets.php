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

    public function __construct(Atlas_Solar_Configurator_Session $session)
    {
        $this->session = $session;
    }

    public function register(): void
    {
        wp_register_style(
            'atlas-solar-configurator',
            ASC_PLUGIN_URL . 'public/css/configurator.css',
            [],
            ASC_VERSION
        );

        wp_register_script(
            'atlas-solar-configurator',
            ASC_PLUGIN_URL . 'public/js/configurator.js',
            [],
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
            ]
        );
    }

    public function enqueue(): void
    {
        wp_enqueue_style('atlas-solar-configurator');
        wp_enqueue_script('atlas-solar-configurator');
    }
}
