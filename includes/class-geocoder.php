<?php
/**
 * Same-origin geocoding proxy for the public configurator.
 *
 * The default upstream is the public Nominatim service. Requests are:
 * - user initiated (no autocomplete);
 * - cached server-side;
 * - globally rate-limited before upstream access;
 * - sent with an identifying User-Agent.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Geocoder
{
    private const ROUTE_NAMESPACE = 'atlas-solar-configurator/v1';
    private const ROUTE = '/geocode';
    private const CACHE_TTL = DAY_IN_SECONDS;
    private const RATE_LIMIT_KEY = 'asc_geocoder_last_upstream_request_at';

    private Atlas_Solar_Configurator_Settings $settings;

    public function __construct(Atlas_Solar_Configurator_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE,
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'geocode'],
                'permission_callback' => '__return_true',
                'args' => [
                    'q' => [
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static function ($value): bool {
                            if (!is_string($value)) {
                                return false;
                            }

                            $length = strlen(trim($value));

                            return $length >= 3 && $length <= 200;
                        },
                    ],
                ],
            ]
        );
    }

    public function geocode(WP_REST_Request $request)
    {
        $query = trim((string) $request->get_param('q'));

        if (strlen($query) < 3 || strlen($query) > 200) {
            return new WP_Error(
                'asc_invalid_address_query',
                __('Inserisci un indirizzo valido.', 'atlas-solar-configurator'),
                ['status' => 400]
            );
        }

        $options = $this->settings->get_map_options();
        $endpoint = (string) $options['geocoder_endpoint'];

        $cache_key = 'asc_geo_' . md5(strtolower($endpoint . '|' . $query));
        $cached = get_transient($cache_key);

        if (false !== $cached && is_array($cached)) {
            return $this->response(
                [
                    'status' => $this->status_from_candidates($cached),
                    'provider' => 'nominatim-compatible',
                    'cached' => true,
                    'candidates' => $cached,
                ]
            );
        }

        $last_request = (float) get_transient(self::RATE_LIMIT_KEY);
        $now = microtime(true);

        if ($last_request > 0 && ($now - $last_request) < 1.05) {
            return new WP_Error(
                'asc_geocoder_rate_limited',
                __('Attendi un istante e riprova la ricerca.', 'atlas-solar-configurator'),
                [
                    'status' => 429,
                    'retry_after' => 2,
                ]
            );
        }

        set_transient(self::RATE_LIMIT_KEY, (string) $now, 2);

        $url = add_query_arg(
            [
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 5,
                'countrycodes' => 'it',
                'accept-language' => 'it',
                'q' => $query,
            ],
            $endpoint
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 8,
                'redirection' => 2,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'user-agent' => sprintf(
                    'ATLAS-Solar-Configurator/%s (+%s)',
                    ASC_VERSION,
                    home_url('/')
                ),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'asc_geocoder_unavailable',
                __('Il servizio di localizzazione non è disponibile. Riprova tra poco.', 'atlas-solar-configurator'),
                [
                    'status' => 502,
                    'upstream' => $response->get_error_code(),
                ]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if (200 !== $status_code) {
            return new WP_Error(
                'asc_geocoder_upstream_error',
                __('Il servizio di localizzazione ha restituito un errore. Riprova tra poco.', 'atlas-solar-configurator'),
                [
                    'status' => 502,
                    'upstream_status' => $status_code,
                ]
            );
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($decoded)) {
            return new WP_Error(
                'asc_geocoder_invalid_response',
                __('Risposta di localizzazione non valida.', 'atlas-solar-configurator'),
                ['status' => 502]
            );
        }

        $candidates = [];

        foreach (array_slice($decoded, 0, 5) as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['lat'], $item['lon'], $item['display_name'])) {
                continue;
            }

            $latitude = filter_var($item['lat'], FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($item['lon'], FILTER_VALIDATE_FLOAT);

            if (false === $latitude || false === $longitude) {
                continue;
            }

            if (
                $latitude < -90
                || $latitude > 90
                || $longitude < -180
                || $longitude > 180
            ) {
                continue;
            }

            $candidates[] = [
                'id' => isset($item['place_id']) ? (string) $item['place_id'] : '',
                'displayName' => sanitize_text_field((string) $item['display_name']),
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'type' => isset($item['type']) ? sanitize_key((string) $item['type']) : '',
                'category' => isset($item['category']) ? sanitize_key((string) $item['category']) : '',
            ];
        }

        set_transient($cache_key, $candidates, self::CACHE_TTL);

        return $this->response(
            [
                'status' => $this->status_from_candidates($candidates),
                'provider' => 'nominatim-compatible',
                'cached' => false,
                'candidates' => $candidates,
            ]
        );
    }

    private function status_from_candidates(array $candidates): string
    {
        $count = count($candidates);

        if (0 === $count) {
            return 'not_found';
        }

        if (1 === $count) {
            return 'resolved';
        }

        return 'ambiguous';
    }

    private function response(array $payload): WP_REST_Response
    {
        $response = new WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
