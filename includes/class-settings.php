<?php
/**
 * Configurator settings and public-provider configuration.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Settings
{
    public const OPTION_KEY = 'atlas_solar_configurator_map';
    public const SETTINGS_GROUP = 'atlas_solar_configurator_map_group';

    public function register(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_map_options'],
                'default' => $this->get_defaults(),
            ]
        );
    }

    public function get_defaults(): array
    {
        return [
            'geocoder_endpoint' => 'https://nominatim.openstreetmap.org/search',
            'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attribution_label' => '© OpenStreetMap contributors',
            'tile_attribution_url' => 'https://www.openstreetmap.org/copyright',
        ];
    }

    public function get_map_options(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($this->get_defaults(), $saved);
    }

    public function get_frontend_map_config(): array
    {
        $options = $this->get_map_options();

        return [
            'enabled' => true,
            'library' => 'leaflet',
            'libraryVersion' => '1.9.4',
            'geocodeUrl' => rest_url('atlas-solar-configurator/v1/geocode'),
            'tileUrl' => $options['tile_url'],
            'tileAttributionLabel' => $options['tile_attribution_label'],
            'tileAttributionUrl' => $options['tile_attribution_url'],
            'defaultCenter' => [42.5, 12.5],
            'defaultZoom' => 5,
            'propertyZoom' => 19,
            'maxZoom' => 19,
        ];
    }

    public function sanitize_map_options($value): array
    {
        $defaults = $this->get_defaults();
        $value = is_array($value) ? $value : [];

        return [
            'geocoder_endpoint' => $this->sanitize_endpoint(
                $value['geocoder_endpoint'] ?? '',
                $defaults['geocoder_endpoint']
            ),
            'tile_url' => $this->sanitize_tile_url(
                $value['tile_url'] ?? '',
                $defaults['tile_url']
            ),
            'tile_attribution_label' => $this->sanitize_label(
                $value['tile_attribution_label'] ?? '',
                $defaults['tile_attribution_label']
            ),
            'tile_attribution_url' => $this->sanitize_endpoint(
                $value['tile_attribution_url'] ?? '',
                $defaults['tile_attribution_url']
            ),
        ];
    }

    public function get_summary(): array
    {
        return [
            'Plugin version' => ASC_VERSION,
            'Mode' => 'Address + map confirmation demo',
            'ATLAS public boundary' => 'Contract v1 ready; transport adapter foundation available',
            'ATLAS transport' => 'Server-side adapter present; public boundary disconnected',
            'ATLAS credentials' => 'Server-side only; never exposed to frontend',
            'ONE CLICK' => 'ATLAS-owned; not called by WordPress public flow',
            'Geocoder' => 'Enabled through WordPress proxy',
            'Map tiles' => 'Enabled client-side',
            'Mock solar result' => ASC_MOCK_MODE ? 'Enabled' : 'Disabled',
            'Lead transmission' => 'Disabled',
        ];
    }

    private function sanitize_endpoint($value, string $default): string
    {
        $candidate = trim(wp_strip_all_tags((string) $value));

        if ('' === $candidate || !preg_match('#^https?://#i', $candidate)) {
            return $default;
        }

        $sanitized = esc_url_raw($candidate, ['http', 'https']);

        return $sanitized ?: $default;
    }

    private function sanitize_tile_url($value, string $default): string
    {
        $candidate = trim(wp_strip_all_tags((string) $value));

        if (
            '' === $candidate
            || !preg_match('#^https?://#i', $candidate)
            || false === strpos($candidate, '{z}')
            || false === strpos($candidate, '{x}')
            || false === strpos($candidate, '{y}')
        ) {
            return $default;
        }

        $validation_url = str_replace(
            ['{z}', '{x}', '{y}', '{s}'],
            ['0', '0', '0', 'a'],
            $candidate
        );

        if (false === filter_var($validation_url, FILTER_VALIDATE_URL)) {
            return $default;
        }

        return $candidate;
    }

    private function sanitize_label($value, string $default): string
    {
        $candidate = sanitize_text_field((string) $value);

        return '' === $candidate ? $default : $candidate;
    }
}
