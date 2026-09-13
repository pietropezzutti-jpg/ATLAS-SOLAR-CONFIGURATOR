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
    public const SECRETS_OPTION_KEY = 'atlas_solar_configurator_secrets';
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

        register_setting(
            self::SETTINGS_GROUP,
            self::SECRETS_OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_secret_options'],
                'show_in_rest' => false,
                'default' => [
                    'geoapify_api_key' => '',
                ],
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
            'aerial_enabled' => true,
            'aerial_wms_url' => 'https://wms.pcn.minambiente.it/ogc?map=/ms_ogc/WMS_v1.3/raster/ortofoto_colore_12.map',
            'aerial_wms_layers' => 'OI.ORTOIMMAGINI.2012',
            'aerial_attribution_label' => 'Ortofoto AGEA 2009-2012 - MASE / Geoportale Nazionale',
            'aerial_attribution_url' => 'https://geodati.gov.it/geoportale/',
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

    public static function get_geoapify_api_key(): string
    {
        $resolved = self::resolve_geoapify_api_key();

        return $resolved['value'];
    }

    public static function get_geoapify_api_key_source(): string
    {
        $resolved = self::resolve_geoapify_api_key();

        return $resolved['source'];
    }

    public function get_frontend_map_config(): array
    {
        $options = $this->get_map_options();

        return [
            'enabled' => true,
            'library' => 'leaflet',
            'libraryVersion' => '1.9.4',
            'geocodeUrl' => rest_url('atlas-solar-configurator/v1/geocode-smart'),
            'tileUrl' => $options['tile_url'],
            'tileAttributionLabel' => $options['tile_attribution_label'],
            'tileAttributionUrl' => $options['tile_attribution_url'],
            'defaultCenter' => [42.5, 12.5],
            'defaultZoom' => 5,
            'propertyZoom' => 19,
            'maxZoom' => 19,
            'aerial' => [
                'enabled' => (bool) $options['aerial_enabled'],
                'label' => 'Foto aerea',
                'wmsUrl' => $options['aerial_wms_url'],
                'layers' => $options['aerial_wms_layers'],
                'version' => '1.1.1',
                'format' => 'image/png',
                'transparent' => false,
                'attributionLabel' => $options['aerial_attribution_label'],
                'attributionUrl' => $options['aerial_attribution_url'],
                'autoEnableAtPropertyZoom' => true,
                'providers' => [
                    [
                        'id' => 'lombardia-ortofoto-2024',
                        'enabled' => true,
                        'label' => 'Ortofoto Lombardia 2024',
                        'wmsUrl' => 'https://www.cartografia.servizirl.it/arcgis5/services/Ortofoto/Ortofoto_2024/ImageServer/WMSServer',
                        'layers' => '0',
                        'version' => '1.1.1',
                        'format' => 'image/jpeg',
                        'transparent' => false,
                        'attributionLabel' => 'Ortofoto 2024 - Regione Lombardia / MASAF',
                        'attributionUrl' => 'https://www.geoportale.regione.lombardia.it/',
                        'bounds' => [
                            'south' => 44.60,
                            'west' => 8.48,
                            'north' => 46.65,
                            'east' => 11.43,
                        ],
                    ],
                    [
                        'id' => 'emilia-romagna-rer-2023-24',
                        'enabled' => true,
                        'label' => 'Ortofoto Emilia-Romagna 2023-24',
                        'wmsUrl' => 'https://servizigis.regione.emilia-romagna.it/wms/rer2023_24_rgb',
                        'layers' => 'RER2023_24_RGB',
                        'version' => '1.1.1',
                        'format' => 'image/png',
                        'transparent' => false,
                        'attributionLabel' => 'Regione Emilia-Romagna - Ortofoto RER 2023-24 - CC BY 4.0',
                        'attributionUrl' => 'https://geoportale.regione.emilia-romagna.it/',
                        'bounds' => [
                            'south' => 43.703388,
                            'west' => 9.172921,
                            'north' => 45.169558,
                            'east' => 12.850398,
                        ],
                    ],
                ],
            ],
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
            'aerial_enabled' => !isset($value['aerial_enabled'])
                ? (bool) $defaults['aerial_enabled']
                : (bool) $value['aerial_enabled'],
            'aerial_wms_url' => $this->sanitize_endpoint(
                $value['aerial_wms_url'] ?? '',
                $defaults['aerial_wms_url']
            ),
            'aerial_wms_layers' => $this->sanitize_wms_layers(
                $value['aerial_wms_layers'] ?? '',
                $defaults['aerial_wms_layers']
            ),
            'aerial_attribution_label' => $this->sanitize_label(
                $value['aerial_attribution_label'] ?? '',
                $defaults['aerial_attribution_label']
            ),
            'aerial_attribution_url' => $this->sanitize_endpoint(
                $value['aerial_attribution_url'] ?? '',
                $defaults['aerial_attribution_url']
            ),
        ];
    }

    public function sanitize_secret_options($value): array
    {
        $value = is_array($value)
            ? $value
            : [];

        $current = get_option(
            self::SECRETS_OPTION_KEY,
            []
        );

        $current = is_array($current)
            ? $current
            : [];

        $current_key =
            self::normalize_geoapify_api_key(
                $current['geoapify_api_key']
                    ?? ''
            );

        if (
            !empty(
                $value[
                    'geoapify_api_key_clear'
                ]
            )
        ) {
            $api_key = '';
        } else {
            $submitted = isset(
                $value['geoapify_api_key']
            )
                ? trim(
                    (string) $value[
                        'geoapify_api_key'
                    ]
                )
                : '';

            if ('' === $submitted) {
                $api_key = $current_key;
            } else {
                $candidate =
                    self::normalize_geoapify_api_key(
                        $submitted
                    );

                $api_key = '' !== $candidate
                    ? $candidate
                    : $current_key;
            }
        }

        return [
            'geoapify_api_key' => $api_key,
        ];
    }

    public function get_summary(): array
    {
        $options = $this->get_map_options();

        $geoapify_key =
            self::get_geoapify_api_key();

        $geoapify_source =
            self::get_geoapify_api_key_source();

        $source_labels = [
            'constant' => 'Server constant',
            'environment' => 'Server environment',
            'wordpress_admin' => 'WordPress admin (server-side)',
            'none' => 'Not configured',
        ];

        $geoapify_source_label =
            $source_labels[$geoapify_source]
            ?? 'Not configured';

        return [
            'Plugin version' => ASC_VERSION,
            'Mode' => 'Address + map confirmation demo',
            'ATLAS public boundary' => 'Contract v1 ready; transport adapter foundation available',
            'ATLAS transport' => 'Server-side adapter present; public boundary disconnected',
            'ATLAS credentials' => 'Server-side only; never exposed to frontend',
            'ONE CLICK' => 'ATLAS-owned; not called by WordPress public flow',
            'Geocoder' => '' !== $geoapify_key
                ? 'Geoapify primary; Nominatim + ANNCSU fallback'
                : 'Nominatim + ANNCSU fallback; Geoapify key not configured',
            'Geoapify key source' => $geoapify_source_label,
            'Map tiles' => 'Enabled client-side',
            'Public aerial imagery' => !empty($options['aerial_enabled'])
                ? 'Automatic provider registry: Lombardia 2024 + Emilia-Romagna 2023-24 when in coverage; national MASE fallback otherwise'
                : 'Disabled',
            'Mock solar result' => ASC_MOCK_MODE ? 'Enabled' : 'Disabled',
            'Lead transmission' => 'Disabled',
        ];
    }

    private static function resolve_geoapify_api_key(): array
    {
        if (
            defined(
                'ASC_GEOAPIFY_API_KEY'
            )
        ) {
            $constant =
                self::normalize_geoapify_api_key(
                    constant(
                        'ASC_GEOAPIFY_API_KEY'
                    )
                );

            if ('' !== $constant) {
                return [
                    'value' => $constant,
                    'source' => 'constant',
                ];
            }
        }

        $environment =
            self::normalize_geoapify_api_key(
                getenv(
                    'ASC_GEOAPIFY_API_KEY'
                )
            );

        if ('' !== $environment) {
            return [
                'value' => $environment,
                'source' => 'environment',
            ];
        }

        $saved = get_option(
            self::SECRETS_OPTION_KEY,
            []
        );

        $saved = is_array($saved)
            ? $saved
            : [];

        $wordpress_key =
            self::normalize_geoapify_api_key(
                $saved['geoapify_api_key']
                    ?? ''
            );

        if ('' !== $wordpress_key) {
            return [
                'value' => $wordpress_key,
                'source' => 'wordpress_admin',
            ];
        }

        return [
            'value' => '',
            'source' => 'none',
        ];
    }

    private static function normalize_geoapify_api_key(
        $value
    ): string {
        $candidate = trim(
            (string) $value
        );

        if (
            '' === $candidate
            || strlen($candidate) > 512
            || preg_match(
                '/[\x00-\x20\x7F]/',
                $candidate
            )
        ) {
            return '';
        }

        return $candidate;
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

    private function sanitize_wms_layers($value, string $default): string
    {
        $candidate = trim(wp_strip_all_tags((string) $value));

        if ('' === $candidate || !preg_match('/^[A-Za-z0-9_.:,\-]+$/', $candidate)) {
            return $default;
        }

        return $candidate;
    }
}
