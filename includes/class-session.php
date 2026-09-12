<?php
/**
 * Anonymous browser session helper.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Session
{
    private const COOKIE_NAME = 'asc_session_id';

    public function get_session_id(): string
    {
        if (!empty($_COOKIE[self::COOKIE_NAME])) {
            return sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
        }

        return wp_generate_uuid4();
    }
}
