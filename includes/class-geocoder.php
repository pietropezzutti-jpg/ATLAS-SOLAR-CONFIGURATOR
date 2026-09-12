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
 * Civic addresses are never replaced by municipality-centre results. When the
 * free-form lookup cannot resolve an address, the proxy may retry the same
 * address using Nominatim's structured street/city search. If the exact civic
 * address still cannot be resolved, the request remains not_found and the
 * frontend must not treat a street or locality centroid as the property.
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
    private const NEGATIVE_CACHE_TTL = 5 * MINUTE_IN_SECONDS;
    private const CACHE_STRATEGY_VERSION = '4';
    private const RATE_LIMIT_KEY = 'asc_geocoder_last_upstream_request_at';
    private const FALLBACK_DELAY_MICROSECONDS = 1100000;

    private const PROVINCE_CODES = [
        'AG', 'AL', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AT', 'AV', 'BA', 'BG', 'BI', 'BL', 'BN', 'BO', 'BR', 'BS', 'BT', 'BZ',
        'CA', 'CB', 'CE', 'CH', 'CL', 'CN', 'CO', 'CR', 'CS', 'CT', 'CZ', 'EN', 'FC', 'FE', 'FG', 'FI', 'FM', 'FR', 'GE',
        'GO', 'GR', 'IM', 'IS', 'KR', 'LC', 'LE', 'LI', 'LO', 'LT', 'LU', 'MB', 'MC', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT',
        'NA', 'NO', 'NU', 'OR', 'PA', 'PC', 'PD', 'PE', 'PG', 'PI', 'PN', 'PO', 'PR', 'PT', 'PU', 'PV', 'PZ', 'RA', 'RC',
        'RE', 'RG', 'RI', 'RM', 'RN', 'RO', 'SA', 'SI', 'SO', 'SP', 'SR', 'SS', 'SU', 'SV', 'TA', 'TE', 'TN', 'TO', 'TP',
        'TR', 'TS', 'TV', 'UD', 'VA', 'VB', 'VC', 'VE', 'VI', 'VR', 'VT', 'VV',
    ];

    private const MONTH_WORDS = [
        'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
        'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
    ];

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

        $cache_key = 'asc_geo_v' . self::CACHE_STRATEGY_VERSION . '_' . md5(strtolower($endpoint . '|' . $query));
        $cached = get_transient($cache_key);

        if (false !== $cached && is_array($cached) && isset($cached['candidates'])) {
            $cached_candidates = is_array($cached['candidates']) ? $cached['candidates'] : [];

            return $this->response(
                [
                    'status' => $this->status_from_candidates($cached_candidates),
                    'provider' => 'nominatim-compatible',
                    'cached' => true,
                    'fallbackUsed' => !empty($cached['fallbackUsed']),
                    'fallbackAttempted' => !empty($cached['fallbackAttempted']),
                    'fallbackMode' => isset($cached['fallbackMode'])
                        ? sanitize_key((string) $cached['fallbackMode'])
                        : 'none',
                    'effectiveQuery' => isset($cached['effectiveQuery'])
                        ? sanitize_text_field((string) $cached['effectiveQuery'])
                        : $query,
                    'candidates' => $cached_candidates,
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

        $candidates = $this->lookup_free_form_candidates($endpoint, $query);
        if (is_wp_error($candidates)) {
            return $candidates;
        }

        $effective_query = $query;
        $fallback_used = false;
        $fallback_attempted = false;
        $fallback_mode = 'none';

        if (0 === count($candidates)) {
            $structured = $this->build_structured_address($query);

            if (null !== $structured) {
                $fallback_attempted = true;
                $this->wait_before_fallback();

                $structured_candidates = $this->lookup_structured_candidates(
                    $endpoint,
                    $structured['street'],
                    $structured['city']
                );

                if (is_wp_error($structured_candidates)) {
                    return $structured_candidates;
                }

                if (count($structured_candidates) > 0) {
                    $candidates = $structured_candidates;
                    $effective_query = $structured['street'] . ', ' . $structured['city'];
                    $fallback_used = true;
                    $fallback_mode = 'structured_exact';
                }
            }
        }

        $cache_payload = [
            'candidates' => $candidates,
            'fallbackUsed' => $fallback_used,
            'fallbackAttempted' => $fallback_attempted,
            'fallbackMode' => $fallback_mode,
            'effectiveQuery' => $effective_query,
        ];

        set_transient(
            $cache_key,
            $cache_payload,
            count($candidates) > 0 ? self::CACHE_TTL : self::NEGATIVE_CACHE_TTL
        );

        return $this->response(
            [
                'status' => $this->status_from_candidates($candidates),
                'provider' => 'nominatim-compatible',
                'cached' => false,
                'fallbackUsed' => $fallback_used,
                'fallbackAttempted' => $fallback_attempted,
                'fallbackMode' => $fallback_mode,
                'effectiveQuery' => $effective_query,
                'candidates' => $candidates,
            ]
        );
    }

    private function lookup_free_form_candidates(string $endpoint, string $query)
    {
        return $this->lookup_candidates(
            $endpoint,
            [
                'q' => $query,
            ]
        );
    }

    private function lookup_structured_candidates(string $endpoint, string $street, string $city)
    {
        return $this->lookup_candidates(
            $endpoint,
            [
                'street' => $street,
                'city' => $city,
            ]
        );
    }

    private function lookup_candidates(string $endpoint, array $query_args)
    {
        $url = add_query_arg(
            array_merge(
                [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 5,
                    'countrycodes' => 'it',
                    'accept-language' => 'it',
                    'layer' => 'address',
                ],
                $query_args
            ),
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

        return $candidates;
    }

    /**
     * Parse a common Italian free-form address into Nominatim structured fields.
     *
     * Example:
     *   via degli scudi 6 Costa volpino bg
     * becomes:
     *   street = 6 via degli scudi
     *   city   = Costa volpino
     */
    private function build_structured_address(string $query): ?array
    {
        $tokens = $this->normalized_address_tokens($query);
        if (count($tokens) < 4) {
            return null;
        }

        $this->remove_trailing_province_code($tokens);
        $civic_index = $this->find_likely_civic_index($tokens);

        if (null === $civic_index || $civic_index < 2 || $civic_index >= count($tokens) - 1) {
            return null;
        }

        $civic = trim((string) $tokens[$civic_index], " \t\n\r\0\x0B,.;");
        $street_tokens = array_slice($tokens, 0, $civic_index);
        $city_tokens = array_slice($tokens, $civic_index + 1);

        while (
            count($city_tokens) > 1
            && preg_match('/^\d{5}$/', trim((string) $city_tokens[0], " \t\n\r\0\x0B,.;"))
        ) {
            array_shift($city_tokens);
        }

        $street_name = $this->tokens_to_query($street_tokens);
        $city = $this->tokens_to_query($city_tokens);

        if ('' === $street_name || '' === $city || '' === $civic) {
            return null;
        }

        return [
            'street' => $civic . ' ' . $street_name,
            'city' => $city,
        ];
    }

    private function normalized_address_tokens(string $query): array
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($query));
        if (!is_string($normalized) || '' === $normalized) {
            return [];
        }

        $tokens = preg_split('/\s+/u', $normalized);

        return is_array($tokens) ? array_values($tokens) : [];
    }

    private function remove_trailing_province_code(array &$tokens): void
    {
        if (0 === count($tokens)) {
            return;
        }

        $last_index = count($tokens) - 1;
        $last_token = strtoupper(trim((string) $tokens[$last_index], " \t\n\r\0\x0B,.;"));

        if (in_array($last_token, self::PROVINCE_CODES, true)) {
            array_pop($tokens);
        }
    }

    private function find_likely_civic_index(array $tokens): ?int
    {
        for ($index = count($tokens) - 1; $index >= 0; --$index) {
            $token = trim((string) $tokens[$index], " \t\n\r\0\x0B,.;");

            if (!preg_match('/^\d{1,4}[a-zA-Z]?(?:[\/-][a-zA-Z0-9]+)?$/', $token)) {
                continue;
            }

            $next = isset($tokens[$index + 1])
                ? strtolower(trim((string) $tokens[$index + 1], " \t\n\r\0\x0B,.;"))
                : '';

            if (in_array($next, self::MONTH_WORDS, true)) {
                continue;
            }

            $words_after = count($tokens) - $index - 1;
            if ($index >= 2 && $words_after >= 1) {
                return $index;
            }
        }

        return null;
    }

    private function tokens_to_query(array $tokens): string
    {
        $clean = [];

        foreach ($tokens as $token) {
            $candidate = trim((string) $token, " \t\n\r\0\x0B,.;");
            if ('' !== $candidate) {
                $clean[] = $candidate;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $clean)) ?? '');
    }

    private function wait_before_fallback(): void
    {
        usleep(self::FALLBACK_DELAY_MICROSECONDS);
        set_transient(self::RATE_LIMIT_KEY, (string) microtime(true), 2);
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
