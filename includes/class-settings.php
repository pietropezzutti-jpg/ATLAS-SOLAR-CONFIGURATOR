<?php
/**
 * Foundation settings model.
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
            'Mode' => 'Foundation',
            'ATLAS integration' => 'Disabled',
            'Mock mode' => ASC_MOCK_MODE ? 'Enabled' : 'Disabled',
        ];
    }
}
