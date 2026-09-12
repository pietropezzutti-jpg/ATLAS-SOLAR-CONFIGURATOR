<?php
/**
 * Optional Geoapify-first geocoding route for the public configurator.
 *
 * Geoapify is used only when a server-side API key is configured. The key is
 * never exposed to JavaScript, HTML, localStorage or public REST responses.
 * When Geoapify is not configured, returns no candidates, or is temporarily
 * unavailable, the request falls back to the existing Nominatim/ANNCSU
 * geocoder without changing its safety rules.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Geoapify_Geocoder
{
    private const ROUTE_NAMESPACE = 'atlas-solar-configurator/v1';
    private const ROUTE = '/geocode-smart';
    private const ENDPOINT = 'https://api.geoapify.com/v1/geocode/search';
    private const CACHE_TTL = DAY_IN_SECONDS;
    private const NEGATIVE_CACHE_TTL = 5 * MINUTE_IN_SECONDS;
    private const PROVIDER = 'geoapify';

    private Atlas_Solar_Configurator_Geocoder $fallback;

    public function __construct(Atlas_Solar_Configurator_Geocoder $fallback)
    {
        $this->fallback = $fallback;
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

        $api_key = $this->api_key();
        if ('' === $api_key) {
            return $this->fallback->geocode($request);
        }

        $cache_key = 'asc_geoapify_v1_' . md5(strtolower($query));
        $cached = get_transient($cache_key);
        if (false !== $cached && is_array($cached) && isset($cached['candidates'])) {
            $cached_candidates = is_array($cached['candidates']) ? $cached['candidates'] : [];

            if (count($cached_candidates) > 0) {
                return $this->response($query, $cached_candidates, true);
            }

            return $this->fallback->geocode($request);
        }

        $url = add_query_arg(
            [
                'text' => $query,
                'format' => 'json',
                'filter' => 'countrycode:it',
                'lang' => 'it',
                'limit' => 8,
                'apiKey' => $api_key,
            ],
            self::ENDPOINT
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 8,
                'redirection' => 1,
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
            return $this->fallback->geocode($request);
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return $this->fallback->geocode($request);
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
            return $this->fallback->geocode($request);
        }

        $candidates = [];
        foreach (array_slice($decoded['results'], 0, 8) as $item) {
            if (!is_array($item) || !isset($item['lat'], $item['lon'])) {
                continue;
            }

            $latitude = filter_var($item['lat'], FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($item['lon'], FILTER_VALIDATE_FLOAT);
            if (!$this->valid_coordinates($latitude, $longitude)) {
                continue;
            }

            $display_name = isset($item['formatted'])
                ? sanitize_text_field((string) $item['formatted'])
                : '';

            if ('' === $display_name) {
                continue;
            }

            $id_source = isset($item['place_id'])
                ? (string) $item['place_id']
                : $display_name . '|' . $latitude . '|' . $longitude;

            $candidates[] = [
                'id' => 'geoapify-' . substr(md5($id_source), 0, 20),
                'displayName' => $display_name,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'type' => isset($item['result_type']) ? sanitize_key((string) $item['result_type']) : '',
                'category' => 'address',
            ];
        }

        set_transient(
            $cache_key,
            ['candidates' => $candidates],
            count($candidates) > 0 ? self::CACHE_TTL : self::NEGATIVE_CACHE_TTL
        );

        if (0 === count($candidates)) {
            return $this->fallback->geocode($request);
        }

        return $this->response($query, $candidates, false);
    }

    public function configured(): bool
    {
        return '' !== $this->api_key();
    }

    private function api_key(): string
    {
        $value = defined('ASC_GEOAPIFY_API_KEY')
            ? (string) constant('ASC_GEOAPIFY_API_KEY')
            : (string) getenv('ASC_GEOAPIFY_API_KEY');

        $value = trim($value);

        if (
            '' === $value
            || strlen($value) > 512
            || preg_match('/[\r\n]/', $value)
        ) {
            return '';
        }

        return $value;
    }

    private function valid_coordinates($latitude, $longitude): bool
    {
        return false !== $latitude
            && false !== $longitude
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
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

    private function response(string $query, array $candidates, bool $cached): WP_REST_Response
    {
        $response = new WP_REST_Response(
            [
                'status' => $this->status_from_candidates($candidates),
                'provider' => self::PROVIDER,
                'cached' => $cached,
                'fallbackUsed' => false,
                'fallbackAttempted' => false,
                'fallbackMode' => 'none',
                'effectiveQuery' => $query,
                'candidates' => $candidates,
            ],
            200
        );

        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
