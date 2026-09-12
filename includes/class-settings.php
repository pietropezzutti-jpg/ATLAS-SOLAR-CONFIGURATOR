<?php
/**
 * Settings summary model.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Settings
{
    public function get_summary(): array
    {
        return [
            'Plugin version' => ASC_VERSION,
            'Mode' => 'Multi-step demo',
            'ATLAS integration' => 'Disabled',
            'External providers' => 'Disabled',
            'Mock mode' => ASC_MOCK_MODE ? 'Enabled' : 'Disabled',
        ];
    }
}
