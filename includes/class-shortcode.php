<?php
/**
 * Shortcode rendering.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Shortcode
{
    private Atlas_Solar_Configurator_Assets $assets;
    private Atlas_Solar_Configurator_Session $session;

    public function __construct(
        Atlas_Solar_Configurator_Assets $assets,
        Atlas_Solar_Configurator_Session $session
    ) {
        $this->assets = $assets;
        $this->session = $session;
    }

    public function render(): string
    {
        $this->assets->enqueue();

        $session_id = esc_attr($this->session->get_session_id());
        $template = ASC_PLUGIN_DIR . 'templates/configurator.php';

        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }
}
