<?php
/**
 * Same-origin geocoding proxy for the public configurator.
 *
 * Resolution order is deliberately exact-first:
 * 1. Nominatim-compatible free-form exact address;
 * 2. Nominatim-compatible structured street/city exact address;
 * 3. ANNCSU open-data exact civic lookup through a configurable server-side
 *    mirror endpoint.
 *
 * A municipality/street centroid is never promoted to property position. The
 * ANNCSU fallback accepts only records matching municipality, street and civic,
 * with valid coordinates and not flagged out_of_bounds. The default ANNCSU
 * endpoint is a community open-data mirror, not an official Agenzia delle
 * Entrate API, and can be overridden/disabled with ASC_ANNCSU_ADDRESS_API_URL.
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
    private const CACHE_STRATEGY_VERSION = '8';
    private const RATE_LIMIT_KEY = 'asc_geocoder_last_upstream_request_at';
    private const FALLBACK_DELAY_MICROSECONDS = 1100000;

    private const ANNCSU_DEFAULT_ENDPOINT = 'https://developers.coseerobe.it/api/v1/anncsu-indirizzi-slim';
    private const ANNCSU_PROVIDER = 'anncsu-community-open-data';

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

    private const STREET_TYPES = [
        'via', 'viale', 'corso', 'piazza', 'piazzale', 'vicolo', 'strada', 'largo',
        'contrada', 'localita', 'località', 'frazione', 'salita', 'discesa', 'borgo',
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
                    'provider' => isset($cached['provider'])
                        ? sanitize_key((string) $cached['provider'])
                        : 'nominatim-compatible',
                    'cached' => true,
                    'fallbackUsed' => !empty($cached['fallbackUsed']),
                    'fallbackAttempted' => !empty($cached['fallbackAttempted']),
                    'fallbackMode' => isset($cached['fallbackMode'])
                        ? sanitize_key((string) $cached['fallbackMode'])
                        : 'none',
                    'effectiveQuery' => isset($cached['effectiveQuery'])
                        ? sanitize_text_field((string) $cached['effectiveQuery'])
                        : $query,
                    'manualFallback' => isset($cached['manualFallback'])
                        && is_array($cached['manualFallback'])
                            ? $cached['manualFallback']
                            : null,
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

        $provider = 'nominatim-compatible';
        $structured = $this->build_structured_address($query);
        $requested_civic = $this->exact_civic_for_query($query);

        $candidates = $this->lookup_free_form_candidates($endpoint, $query);
        if (is_wp_error($candidates)) {
            return $candidates;
        }

        if ('' !== $requested_civic) {
            $candidates = $this->filter_exact_civic_candidates(
                $candidates,
                $requested_civic
            );
        }

        $effective_query = $query;
        $fallback_used = false;
        $fallback_attempted = false;
        $fallback_mode = 'none';
        $manual_fallback = null;

        if (0 === count($candidates) && null !== $structured) {
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

            $structured_candidates = $this->filter_exact_civic_candidates(
                $structured_candidates,
                $structured['civic']
            );

            if (count($structured_candidates) > 0) {
                $candidates = $structured_candidates;
                $effective_query = $structured['street'] . ', ' . $structured['city'];
                $fallback_used = true;
                $fallback_mode = 'structured_exact';
            }
        }

        if (0 === count($candidates) && null !== $structured) {
            $anncsu_endpoint = $this->anncsu_endpoint();

            if ('' !== $anncsu_endpoint) {
                $fallback_attempted = true;

                $anncsu_candidates = $this->lookup_anncsu_exact_candidates(
                    $anncsu_endpoint,
                    $structured
                );

                if (
                    !is_wp_error($anncsu_candidates)
                    && count($anncsu_candidates) > 0
                ) {
                    $candidates = $anncsu_candidates;
                    $provider = self::ANNCSU_PROVIDER;
                    $effective_query = $structured['street_name'] . ' ' . $structured['civic'] . ', ' . $structured['city'];
                    $fallback_used = true;
                    $fallback_mode = 'anncsu_exact';
                }
            }
        }

        /*
         * Exact civic evidence is unavailable.
         *
         * A street-level result may now be used only as a map reference.
         * It is deliberately kept out of candidates so that it can never
         * become a confirmed property position without an explicit map click.
         */
        if (0 === count($candidates) && null !== $structured) {
            $fallback_attempted = true;
            $this->wait_before_fallback();

            $street_reference_candidates = $this->lookup_street_reference_candidates(
                $endpoint,
                $structured['street_name'],
                $structured['city']
            );

            if (
                !is_wp_error($street_reference_candidates)
                && count($street_reference_candidates) > 0
            ) {
                $manual_fallback = $street_reference_candidates[0];
                $manual_fallback['referenceOnly'] = true;
                $effective_query = $structured['street_name'] . ', ' . $structured['city'];
                $fallback_used = true;
                $fallback_mode = 'manual_map';
            }
        }

        $cache_payload = [
            'provider' => $provider,
            'candidates' => $candidates,
            'manualFallback' => $manual_fallback,
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
                'provider' => $provider,
                'cached' => false,
                'fallbackUsed' => $fallback_used,
                'fallbackAttempted' => $fallback_attempted,
                'fallbackMode' => $fallback_mode,
                'effectiveQuery' => $effective_query,
                'manualFallback' => $manual_fallback,
                'candidates' => $candidates,
            ]
        );
    }

    /**
     * Return only ANNCSU exact-civic candidates for an address-like query.
     *
     * This is used as independent evidence by the Geoapify smart geocoder when
     * Geoapify returns multiple equally strong building/address candidates. It
     * deliberately reuses the existing ANNCSU parser, endpoint and exact-record
     * validator instead of introducing a second client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function anncsu_exact_candidates_for_query(string $query): array
    {
        $structured = $this->build_structured_address($query);
        if (null === $structured) {
            return [];
        }

        $anncsu_endpoint = $this->anncsu_endpoint();
        if ('' === $anncsu_endpoint) {
            return [];
        }

        $candidates = $this->lookup_anncsu_exact_candidates(
            $anncsu_endpoint,
            $structured
        );

        return is_array($candidates) ? $candidates : [];
    }

    /**
     * Return Nominatim structured exact candidates for an address-like query.
     *
     * This exposes the existing structured lookup as an internal boundary for
     * Geoapify candidate tie-breaking. It does not introduce a new Nominatim
     * client, endpoint, public REST API or configuration surface.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nominatim_structured_exact_candidates_for_query(string $query): array
    {
        $structured = $this->build_structured_address($query);
        if (null === $structured) {
            return [];
        }

        $options = $this->settings->get_map_options();
        $endpoint = isset($options['geocoder_endpoint'])
            ? trim((string) $options['geocoder_endpoint'])
            : '';

        if ('' === $endpoint) {
            return [];
        }

        $candidates = $this->lookup_structured_candidates(
            $endpoint,
            $structured['street'],
            $structured['city']
        );

        if (!is_array($candidates)) {
            return [];
        }

        return $this->filter_exact_civic_candidates(
            $candidates,
            $structured['civic']
        );
    }

    /**
     * Return a civic detectable from the query even if municipality
     * information is insufficient for structured lookup.
     */
    public function exact_civic_for_query(string $query): string
    {
        $tokens = $this->normalized_address_tokens($query);
        if (count($tokens) < 3) {
            return '';
        }

        $this->remove_trailing_province_code($tokens);

        $civic_index = $this->find_likely_civic_index(
            $tokens,
            false
        );

        if (null === $civic_index) {
            return '';
        }

        return trim(
            (string) $tokens[$civic_index],
            " \t\n\r\0\x0B,.;"
        );
    }

    private function lookup_free_form_candidates(string $endpoint, string $query)
    {
        return $this->lookup_nominatim_candidates(
            $endpoint,
            [
                'q' => $query,
            ]
        );
    }

    private function lookup_structured_candidates(string $endpoint, string $street, string $city)
    {
        return $this->lookup_nominatim_candidates(
            $endpoint,
            [
                'street' => $street,
                'city' => $city,
            ]
        );
    }

    private function lookup_street_reference_candidates(
        string $endpoint,
        string $street,
        string $city
    ) {
        $candidates = $this->lookup_nominatim_candidates(
            $endpoint,
            [
                'street' => $street,
                'city' => $city,
            ],
            false
        );

        if (!is_array($candidates)) {
            return $candidates;
        }

        return $this->filter_street_reference_candidates($candidates);
    }

    private function lookup_nominatim_candidates(
        string $endpoint,
        array $query_args,
        bool $address_only = true
    ) {
        $base_args = [
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 5,
            'countrycodes' => 'it',
            'accept-language' => 'it',
        ];

        if ($address_only) {
            $base_args['layer'] = 'address';
        }

        $url = add_query_arg(
            array_merge(
                $base_args,
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

            if (!$this->valid_coordinates($latitude, $longitude)) {
                continue;
            }

            $address = isset($item['address']) && is_array($item['address'])
                ? $item['address']
                : [];

            $candidates[] = [
                'id' => isset($item['place_id']) ? (string) $item['place_id'] : '',
                'displayName' => sanitize_text_field((string) $item['display_name']),
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'type' => isset($item['type']) ? sanitize_key((string) $item['type']) : '',
                'category' => isset($item['category']) ? sanitize_key((string) $item['category']) : '',
                'houseNumber' => isset($address['house_number'])
                    ? sanitize_text_field((string) $address['house_number'])
                    : '',
            ];
        }

        return $candidates;
    }

    private function filter_street_reference_candidates(array $candidates): array
    {
        return array_values(
            array_filter(
                $candidates,
                static function (array $candidate): bool {
                    $category = isset($candidate['category'])
                        ? sanitize_key((string) $candidate['category'])
                        : '';

                    return 'highway' === $category;
                }
            )
        );
    }

    private function filter_exact_civic_candidates(array $candidates, string $civic): array
    {
        return array_values(
            array_filter(
                $candidates,
                function (array $candidate) use ($civic): bool {
                    return $this->candidate_has_exact_civic($candidate, $civic);
                }
            )
        );
    }

    private function candidate_has_exact_civic(array $candidate, string $civic): bool
    {
        $house_number = isset($candidate['houseNumber'])
            ? trim((string) $candidate['houseNumber'])
            : '';

        return '' !== $house_number
            && $this->same_civic($house_number, $civic);
    }

    private function lookup_anncsu_exact_candidates(string $endpoint, array $structured)
    {
        $url = add_query_arg(
            [
                'NOME_COMUNE' => 'ilike.*' . $structured['city'] . '*',
                'ODONIMO' => 'ilike.*' . $structured['street_name'] . '*',
                'CIVICO' => 'eq.' . $structured['civic'],
                'order' => 'NOME_COMUNE.asc,ODONIMO.asc,CIVICO.asc',
                'limit' => 25,
            ],
            $endpoint
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
            return new WP_Error(
                'asc_anncsu_provider_unavailable',
                __('Il servizio civici secondario non è disponibile. Riprova tra poco.', 'atlas-solar-configurator'),
                [
                    'status' => 502,
                    'upstream' => $response->get_error_code(),
                ]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if (200 !== $status_code) {
            return new WP_Error(
                'asc_anncsu_provider_error',
                __('Il servizio civici secondario ha restituito un errore. Riprova tra poco.', 'atlas-solar-configurator'),
                [
                    'status' => 502,
                    'upstream_status' => $status_code,
                ]
            );
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($decoded)) {
            return new WP_Error(
                'asc_anncsu_provider_invalid_response',
                __('Risposta del servizio civici non valida.', 'atlas-solar-configurator'),
                ['status' => 502]
            );
        }

        $candidates = [];

        foreach (array_slice($decoded, 0, 25) as $item) {
            if (!is_array($item) || !$this->anncsu_record_is_exact($item, $structured)) {
                continue;
            }

            $latitude_raw = $item['latitude'] ?? ($item['COORD_Y_COMUNE'] ?? null);
            $longitude_raw = $item['longitude'] ?? ($item['COORD_X_COMUNE'] ?? null);
            $latitude = filter_var($latitude_raw, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($longitude_raw, FILTER_VALIDATE_FLOAT);

            if (!$this->valid_coordinates($latitude, $longitude)) {
                continue;
            }

            $street = sanitize_text_field((string) ($item['ODONIMO'] ?? $structured['street_name']));
            $civic = sanitize_text_field((string) ($item['CIVICO'] ?? $structured['civic']));
            $exponent = isset($item['ESPONENTE']) ? trim((string) $item['ESPONENTE']) : '';
            $city = sanitize_text_field((string) ($item['NOME_COMUNE'] ?? $structured['city']));
            $display_civic = $civic . ('' !== $exponent ? '/' . sanitize_text_field($exponent) : '');
            $id_source = isset($item['PROGRESSIVO_ACCESSO'])
                ? (string) $item['PROGRESSIVO_ACCESSO']
                : $street . '|' . $display_civic . '|' . $city . '|' . $latitude . '|' . $longitude;

            $candidates[] = [
                'id' => 'anncsu-' . substr(md5($id_source), 0, 16),
                'displayName' => trim($street . ' ' . $display_civic . ', ' . $city),
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'type' => 'house',
                'category' => 'address',
            ];
        }

        return $candidates;
    }

    private function anncsu_record_is_exact(array $item, array $structured): bool
    {
        $city = isset($item['NOME_COMUNE']) ? (string) $item['NOME_COMUNE'] : '';
        $street = isset($item['ODONIMO']) ? (string) $item['ODONIMO'] : '';
        $civic = isset($item['CIVICO']) ? (string) $item['CIVICO'] : '';

        if (
            !$this->same_label($city, $structured['city'])
            || !$this->same_street($street, $structured['street_name'])
            || !$this->same_civic($civic, $structured['civic'])
        ) {
            return false;
        }

        if (isset($item['out_of_bounds']) && $this->truthy_flag($item['out_of_bounds'])) {
            return false;
        }

        return true;
    }

    private function anncsu_endpoint(): string
    {
        $endpoint = defined('ASC_ANNCSU_ADDRESS_API_URL')
            ? trim((string) constant('ASC_ANNCSU_ADDRESS_API_URL'))
            : self::ANNCSU_DEFAULT_ENDPOINT;

        if ('' === $endpoint) {
            return '';
        }

        $parts = wp_parse_url($endpoint);
        if (
            !is_array($parts)
            || 'https' !== strtolower((string) ($parts['scheme'] ?? ''))
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return '';
        }

        return untrailingslashit($endpoint);
    }

    /**
     * Parse a common Italian free-form address into exact structured fields.
     *
     * Example:
     *   via esempio 39 Comune Test
     * becomes street=39 via esempio, street_name=via esempio,
     * civic=39 and city=Comune Test.
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
            count($city_tokens) > 0
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
            'street_name' => $street_name,
            'civic' => $civic,
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

    private function find_likely_civic_index(
        array $tokens,
        bool $require_city_after = true
    ): ?int {
        for ($index = count($tokens) - 1; $index >= 0; --$index) {
            $token = trim(
                (string) $tokens[$index],
                " \t\n\r\0\x0B,.;"
            );

            if (!preg_match(
                '/^\d{1,4}[a-zA-Z]?(?:[\/-][a-zA-Z0-9]+)?$/',
                $token
            )) {
                continue;
            }

            $next = isset($tokens[$index + 1])
                ? strtolower(
                    trim(
                        (string) $tokens[$index + 1],
                        " \t\n\r\0\x0B,.;"
                    )
                )
                : '';

            if (in_array($next, self::MONTH_WORDS, true)) {
                continue;
            }

            $words_after = count($tokens) - $index - 1;

            if (
                $index >= 2
                && (!$require_city_after || $words_after >= 1)
            ) {
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

    private function normalize_label(string $value): string
    {
        $value = strtolower(remove_accents(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', is_string($value) ? $value : '') ?? '');
    }

    private function same_label(string $left, string $right): bool
    {
        $left_normalized = $this->normalize_label($left);
        $right_normalized = $this->normalize_label($right);

        return '' !== $left_normalized && $left_normalized === $right_normalized;
    }

    private function same_street(string $left, string $right): bool
    {
        $left_normalized = $this->normalize_label($left);
        $right_normalized = $this->normalize_label($right);

        if ('' === $left_normalized || '' === $right_normalized) {
            return false;
        }

        if ($left_normalized === $right_normalized) {
            return true;
        }

        return $this->strip_street_type($left_normalized) === $this->strip_street_type($right_normalized);
    }

    private function strip_street_type(string $value): string
    {
        $tokens = preg_split('/\s+/', $value);
        if (!is_array($tokens) || count($tokens) < 2) {
            return $value;
        }

        if (in_array($tokens[0], self::STREET_TYPES, true)) {
            array_shift($tokens);
        }

        return implode(' ', $tokens);
    }

    private function same_civic(string $left, string $right): bool
    {
        $normalize = static function (string $value): string {
            $normalized = strtoupper(remove_accents(trim($value)));

            return preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
        };

        $left_normalized = $normalize($left);
        $right_normalized = $normalize($right);

        return '' !== $left_normalized && $left_normalized === $right_normalized;
    }

    private function truthy_flag($value): bool
    {
        if (true === $value || 1 === $value) {
            return true;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 't', 'yes', 'y'], true);
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
