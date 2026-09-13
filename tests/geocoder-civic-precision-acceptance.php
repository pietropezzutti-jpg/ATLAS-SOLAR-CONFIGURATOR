<?php
/**
 * R5 national civic-precision contract acceptance.
 *
 * All addresses are synthetic.
 * All upstream HTTP is intercepted.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "FAIL=WORDPRESS_NOT_LOADED\n");
    exit(1);
}

function asc_r5_assert(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL={$label}\n");
        exit(1);
    }

    fwrite(STDOUT, "{$label}=PASS\n");
}

if (!defined('ASC_GEOAPIFY_API_KEY')) {
    define('ASC_GEOAPIFY_API_KEY', 'r5-test-server-side-key');
}

if (!defined('ASC_ANNCSU_ADDRESS_API_URL')) {
    define(
        'ASC_ANNCSU_ADDRESS_API_URL',
        'https://anncsu.example.test/api/v1/anncsu-indirizzi-slim'
    );
}

function asc_r5_nominatim(
    string $id,
    string $display,
    float $lat,
    float $lon,
    string $house = ''
): array {
    $address = [
        'road' => 'Via Esempio',
        'city' => 'Comune Test',
    ];

    if ('' !== $house) {
        $address['house_number'] = $house;
    }

    return [
        'place_id' => $id,
        'display_name' => $display,
        'lat' => $lat,
        'lon' => $lon,
        'type' => '' === $house ? 'secondary' : 'house',
        'category' => '' === $house ? 'highway' : 'place',
        'address' => $address,
    ];
}

$filter = static function ($preempt, $args, $url) {
    $url = (string) $url;

    $query_string = (string) (
        wp_parse_url($url, PHP_URL_QUERY) ?? ''
    );

    $params = [];
    parse_str($query_string, $params);

    if (false !== strpos(
        $url,
        'api.geoapify.com/v1/geocode/search'
    )) {
        $text = strtolower((string) ($params['text'] ?? ''));

        if (false !== strpos($text, '20')) {
            $results = [[
                'place_id' => 'geo-street-only',
                'formatted' => 'Via Esempio, Comune Test, Italia',
                'lat' => 45.100000,
                'lon' => 10.100000,
                'result_type' => 'building',
                'rank' => [
                    'confidence' => 0.95,
                    'confidence_building_level' => 0.90,
                    'match_type' => 'full_match',
                ],
            ]];
        } elseif (false !== strpos($text, '21')) {
            $results = [[
                'place_id' => 'geo-exact-21',
                'formatted' => 'Via Esempio 21, Comune Test, Italia',
                'lat' => 45.210000,
                'lon' => 10.210000,
                'housenumber' => '21',
                'result_type' => 'building',
                'rank' => [
                    'confidence' => 0.80,
                    'confidence_building_level' => 0.60,
                    'match_type' => 'full_match',
                ],
            ]];
        } else {
            $results = [];
        }

        return [
            'headers' => [],
            'body' => wp_json_encode(['results' => $results]),
            'response' => [
                'code' => 200,
                'message' => 'OK',
            ],
            'cookies' => [],
            'filename' => null,
        ];
    }

    if (false !== strpos($url, 'format=jsonv2')) {
        if (isset($params['q'])) {
            $results = [
                asc_r5_nominatim(
                    'free-form-street',
                    'Via Esempio, Comune Test, Italia',
                    45.000000,
                    10.000000
                ),
            ];
        } elseif (isset($params['street'])) {
            $street = strtolower(
                trim((string) $params['street'])
            );

            if (0 === strpos($street, '18 ')) {
                $results = [
                    asc_r5_nominatim(
                        'structured-18',
                        'Via Esempio 18, Comune Test, Italia',
                        45.180000,
                        10.180000,
                        '18'
                    ),
                ];
            } elseif (0 === strpos($street, '20 ')) {
                $results = [
                    asc_r5_nominatim(
                        'structured-20',
                        'Via Esempio 20, Comune Test, Italia',
                        45.200000,
                        10.200000,
                        '20'
                    ),
                ];
            } else {
                $results = [
                    asc_r5_nominatim(
                        'structured-street-only',
                        'Via Esempio, Comune Test, Italia',
                        45.000000,
                        10.000000
                    ),
                ];
            }
        } else {
            $results = [];
        }

        return [
            'headers' => [],
            'body' => wp_json_encode($results),
            'response' => [
                'code' => 200,
                'message' => 'OK',
            ],
            'cookies' => [],
            'filename' => null,
        ];
    }

    if (false !== strpos($url, 'anncsu-indirizzi-slim')) {
        $civic = (string) ($params['CIVICO'] ?? '');

        if ('eq.23' === $civic) {
            return [
                'headers' => [],
                'body' => wp_json_encode(['error' => 'synthetic unavailable']),
                'response' => [
                    'code' => 503,
                    'message' => 'Service Unavailable',
                ],
                'cookies' => [],
                'filename' => null,
            ];
        }

        $records = 'eq.19' === $civic
            ? [[
                'PROGRESSIVO_ACCESSO' => 'synthetic-access-19',
                'NOME_COMUNE' => 'Comune Test',
                'ODONIMO' => 'VIA ESEMPIO',
                'CIVICO' => '19',
                'latitude' => 45.190000,
                'longitude' => 10.190000,
                'out_of_bounds' => false,
            ]]
            : [];

        return [
            'headers' => [],
            'body' => wp_json_encode($records),
            'response' => [
                'code' => 200,
                'message' => 'OK',
            ],
            'cookies' => [],
            'filename' => null,
        ];
    }

    return $preempt;
};

add_filter('pre_http_request', $filter, 10, 3);

$settings = new Atlas_Solar_Configurator_Settings();
$fallback = new Atlas_Solar_Configurator_Geocoder($settings);
$smart = new Atlas_Solar_Configurator_Geoapify_Geocoder(
    $fallback
);

$options = $settings->get_map_options();
$endpoint = (string) $options['geocoder_endpoint'];

$run_fallback = static function (
    string $query
) use ($fallback, $endpoint) {
    delete_transient(
        'asc_geocoder_last_upstream_request_at'
    );

    delete_transient(
        'asc_geo_v7_' .
        md5(strtolower($endpoint . '|' . $query))
    );

    $request = new WP_REST_Request(
        'GET',
        '/atlas-solar-configurator/v1/geocode'
    );

    $request->set_param('q', $query);

    return $fallback->geocode($request);
};

$run_smart = static function (
    string $query
) use ($smart, $endpoint) {
    delete_transient(
        'asc_geocoder_last_upstream_request_at'
    );

    delete_transient(
        'asc_geo_v7_' .
        md5(strtolower($endpoint . '|' . $query))
    );

    delete_transient(
        'asc_geoapify_v7_' .
        md5(strtolower($query))
    );

    $request = new WP_REST_Request(
        'GET',
        '/atlas-solar-configurator/v1/geocode-smart'
    );

    $request->set_param('q', $query);

    return $smart->geocode($request);
};

/*
 * Free-form road-only candidate cannot resolve civic 18.
 * Structured exact evidence may resolve it.
 */
$response18 = $run_fallback(
    'Via Esempio 18 Comune Test'
);

$data18 = $response18->get_data();

asc_r5_assert(
    'resolved' === ($data18['status'] ?? null),
    'R5_STRUCTURED_EXACT_RESOLVED'
);

asc_r5_assert(
    true === ($data18['fallbackAttempted'] ?? null)
        && 'structured_exact'
            === ($data18['fallbackMode'] ?? null),
    'R5_STREET_ONLY_FORCES_STRUCTURED_EXACT'
);

asc_r5_assert(
    '18'
        === ($data18['candidates'][0]['houseNumber'] ?? null),
    'R5_STRUCTURED_HOUSE_NUMBER_EXACT'
);

/*
 * Structured road-only candidate must also be rejected,
 * allowing ANNCSU exact civic evidence.
 */
$response19 = $run_fallback(
    'Via Esempio 19 Comune Test'
);

$data19 = $response19->get_data();

asc_r5_assert(
    'resolved' === ($data19['status'] ?? null)
        && 'anncsu-community-open-data'
            === ($data19['provider'] ?? null),
    'R5_ANNCSU_EXACT_RESOLVED'
);

/*
 * No civic: street-level behaviour is preserved.
 */
$responseStreet = $run_fallback(
    'Via Esempio Comune Test'
);

$dataStreet = $responseStreet->get_data();

asc_r5_assert(
    'resolved' === ($dataStreet['status'] ?? null),
    'R5_STREET_WITHOUT_CIVIC_PRESERVED'
);

/*
 * Civic present, municipality absent:
 * street-level candidate still cannot be property position.
 */
$responseNoCity = $run_fallback(
    'Via Esempio 22'
);

$dataNoCity = $responseNoCity->get_data();

asc_r5_assert(
    'not_found' === ($dataNoCity['status'] ?? null),
    'R5_CIVIC_WITHOUT_CITY_STREET_ONLY_REJECTED'
);

/*
 * Exact civic unavailable and secondary ANNCSU provider unavailable:
 * street-level evidence may center the map but must not become a property
 * candidate. The browser must require an explicit map click.
 */
$response23 = $run_fallback(
    'Via Esempio 23 Comune Test'
);

$data23 = $response23->get_data();

asc_r5_assert(
    'not_found' === ($data23['status'] ?? null)
        && 0 === count($data23['candidates'] ?? []),
    'R6_MANUAL_MAP_DOES_NOT_PROMOTE_STREET_TO_PROPERTY'
);

asc_r5_assert(
    'manual_map' === ($data23['fallbackMode'] ?? null)
        && is_array($data23['manualFallback'] ?? null)
        && true === ($data23['manualFallback']['referenceOnly'] ?? null),
    'R6_ANNCSU_ERROR_DEGRADES_TO_MANUAL_MAP'
);

/*
 * Geoapify building-like candidate without housenumber
 * cannot satisfy civic 20; exact fallback must win.
 */
$response20 = $run_smart(
    'Via Esempio 20 Comune Test'
);

$data20 = $response20->get_data();

asc_r5_assert(
    'resolved' === ($data20['status'] ?? null)
        && 'nominatim-compatible'
            === ($data20['provider'] ?? null),
    'R5_GEOAPIFY_MISSING_CIVIC_NOT_PROMOTED'
);

asc_r5_assert(
    'structured_exact'
        === ($data20['fallbackMode'] ?? null),
    'R5_GEOAPIFY_TO_EXACT_FALLBACK'
);

/*
 * Geoapify exact civic evidence remains valid.
 */
$response21 = $run_smart(
    'Via Esempio 21 Comune Test'
);

$data21 = $response21->get_data();

asc_r5_assert(
    'resolved' === ($data21['status'] ?? null)
        && 'geoapify' === ($data21['provider'] ?? null),
    'R5_GEOAPIFY_EXACT_CIVIC_RESOLVED'
);

asc_r5_assert(
    '21'
        === ($data21['candidates'][0]['houseNumber'] ?? null),
    'R5_GEOAPIFY_HOUSE_NUMBER_EXACT'
);

remove_filter('pre_http_request', $filter, 10);

fwrite(STDOUT, "REAL_LOCATION_FIXTURE=False\n");
fwrite(
    STDOUT,
    "CIVIC_STREET_PROMOTION_ALLOWED=False\n"
);
fwrite(
    STDOUT,
    "STREET_WITHOUT_CIVIC_PRESERVED=True\n"
);
fwrite(
    STDOUT,
    "KEYLESS_MANUAL_MAP_FALLBACK=True\n"
);
fwrite(
    STDOUT,
    "FINAL=PASS_PUBLIC_CONFIGURATOR_R6_KEYLESS_MANUAL_MAP_ACCEPTANCE\n"
);
