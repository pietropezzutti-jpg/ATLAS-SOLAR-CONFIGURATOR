<?php
/**
 * Geoapify candidate-quality R4 + building-snap R1 acceptance.
 *
 * All HTTP is intercepted. No external request is executed.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "FAIL=WORDPRESS_NOT_LOADED\n");
    exit(1);
}

if (!class_exists('Atlas_Solar_Configurator_Geoapify_Geocoder')) {
    fwrite(STDERR, "FAIL=GEOAPIFY_GEOCODER_CLASS_NOT_LOADED\n");
    exit(1);
}

function asc_geoapify_assert(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL={$label}\n");
        exit(1);
    }

    fwrite(STDOUT, "{$label}=PASS\n");
}

if (!defined('ASC_GEOAPIFY_API_KEY')) {
    define('ASC_GEOAPIFY_API_KEY', 'test-server-side-key');
}
$configured_key = (string) constant('ASC_GEOAPIFY_API_KEY');

function asc_building_feature(float $lat, float $lon): array
{
    return [
        'type' => 'Feature',
        'properties' => [
            'feature_type' => 'building',
            'lat' => $lat,
            'lon' => $lon,
        ],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [
                [
                    [$lon - 0.00005, $lat - 0.00005],
                    [$lon + 0.00005, $lat - 0.00005],
                    [$lon + 0.00005, $lat + 0.00005],
                    [$lon - 0.00005, $lat + 0.00005],
                    [$lon - 0.00005, $lat - 0.00005],
                ],
            ],
        ],
    ];
}

function asc_building_result(
    string $place_id,
    string $formatted,
    float $lat,
    float $lon
): array {
    $house_number = '';

    if (preg_match('/\b(\d{1,4})\b/u', $formatted, $matches)) {
        $house_number = (string) $matches[1];
    }

    return [
        'place_id' => $place_id,
        'formatted' => $formatted,
        'lat' => $lat,
        'lon' => $lon,
        'housenumber' => $house_number,
        'result_type' => 'building',
        'rank' => [
            'confidence' => 0.97,
            'confidence_building_level' => 0.91,
            'match_type' => 'full_match',
        ],
    ];
}

function asc_geoapify_cache_key(string $query): string
{
    return 'asc_geoapify_v7_' . md5(strtolower($query));
}

function asc_delete_geoapify_cache(string $query): void
{
    delete_transient(asc_geoapify_cache_key($query));
}

function asc_anncsu_exact_record(string $street, string $civic, string $city, float $lat, float $lon): array
{
    return [
        'NOME_COMUNE' => $city,
        'ODONIMO' => $street,
        'CIVICO' => $civic,
        'latitude' => $lat,
        'longitude' => $lon,
    ];
}

function asc_nominatim_result(string $place_id, string $display_name, float $lat, float $lon): array
{
    $house_number = '';

    if (preg_match('/\b(\d{1,4})\b/u', $display_name, $matches)) {
        $house_number = (string) $matches[1];
    }

    return [
        'place_id' => $place_id,
        'display_name' => $display_name,
        'lat' => $lat,
        'lon' => $lon,
        'type' => 'house',
        'category' => 'place',
        'address' => [
            'house_number' => $house_number,
        ],
    ];
}

$mode = 'quality_filter';
$http_calls = [];
$filter = static function ($preempt, $args, $url) use (&$http_calls, &$mode) {
    $http_calls[] = (string) $url;

    if (false !== strpos((string) $url, 'api.geoapify.com/v1/geocode/search')) {
        if ('admin_only_address_like' === $mode) {
            $results = [
                [
                    'place_id' => 'synthetic-city-centre',
                    'formatted' => 'Comune Test, Provincia Test, Italia',
                    'lat' => 45.905000,
                    'lon' => 10.210000,
                    'result_type' => 'city',
                    'rank' => [
                        'confidence' => 0.94,
                        'match_type' => 'match_by_city_or_district',
                    ],
                ],
                [
                    'place_id' => 'synthetic-district-centre',
                    'formatted' => 'Quartiere Test, Comune Test, Italia',
                    'lat' => 45.906000,
                    'lon' => 10.211000,
                    'result_type' => 'district',
                    'rank' => [
                        'confidence' => 0.90,
                        'match_type' => 'match_by_district',
                    ],
                ],
            ];
        } elseif ('tiebreaker_clear_winner' === $mode || 'tiebreaker_nominatim_clear_winner' === $mode) {
            $results = [
                asc_building_result(
                    'synthetic-building-farther',
                    'Via Esempio 41/A, 25000 Comune Test, Italia',
                    45.900000,
                    10.200000
                ),
                asc_building_result(
                    'synthetic-building-nearer',
                    'Via Esempio 41, 25000 Comune Test, Italia',
                    45.901000,
                    10.201000
                ),
            ];
        } elseif (
            'tiebreaker_ambiguous' === $mode
            || 'tiebreaker_anncsu_unavailable' === $mode
            || 'tiebreaker_nominatim_not_discriminating' === $mode
            || 'tiebreaker_nominatim_ambiguous' === $mode
            || 'tiebreaker_nominatim_unavailable' === $mode
            || 'tiebreaker_anncsu_ambiguous' === $mode
        ) {
            $results = [
                asc_building_result(
                    'synthetic-building-option-a',
                    'Via Esempio 43/A, 25000 Comune Test, Italia',
                    45.902000,
                    10.202000
                ),
                asc_building_result(
                    'synthetic-building-option-b',
                    'Via Esempio 43/B, 25000 Comune Test, Italia',
                    45.902050,
                    10.202050
                ),
            ];
        } elseif ('true_address_ambiguity' === $mode) {
            $results = [
                asc_building_result(
                    'synthetic-building-a',
                    'Via Esempio 39, 25000 Comune Test, Italia',
                    45.900123,
                    10.200456
                ),
                asc_building_result(
                    'synthetic-building-b',
                    'Via Esempio 39/B, 25000 Comune Test, Italia',
                    45.900523,
                    10.200856
                ),
            ];
        } else {
            $results = [
                asc_building_result(
                    'synthetic-place-39',
                    'Via Esempio 39, 25000 Comune Test, Italia',
                    45.900123,
                    10.200456
                ),
                asc_building_result(
                    'synthetic-place-39',
                    'Via Esempio 39, Comune Test, Provincia Test, Italia',
                    45.900123,
                    10.200456
                ),
                [
                    'place_id' => 'synthetic-municipality-centre',
                    'formatted' => 'Comune Test, Provincia Test, Italia',
                    'lat' => 45.905000,
                    'lon' => 10.210000,
                    'result_type' => 'municipality',
                    'rank' => [
                        'confidence' => 0.88,
                        'match_type' => 'match_by_city_or_disrict',
                    ],
                ],
            ];
        }

        return [
            'headers' => [],
            'body' => wp_json_encode(['results' => $results]),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    if (false !== strpos((string) $url, 'format=jsonv2')) {
        if ('tiebreaker_nominatim_clear_winner' === $mode) {
            $results = [
                asc_nominatim_result(
                    'nominatim-structured-clear',
                    'Via Esempio 41, Comune Test, Italia',
                    45.901010,
                    10.201010
                ),
            ];
        } elseif ('tiebreaker_nominatim_not_discriminating' === $mode) {
            $results = [
                asc_nominatim_result(
                    'nominatim-structured-middle',
                    'Via Esempio 43, Comune Test, Italia',
                    45.902025,
                    10.202025
                ),
            ];
        } elseif ('tiebreaker_nominatim_ambiguous' === $mode) {
            $results = [
                asc_nominatim_result(
                    'nominatim-structured-a',
                    'Via Esempio 43, Comune Test, Italia',
                    45.902010,
                    10.202010
                ),
                asc_nominatim_result(
                    'nominatim-structured-b',
                    'Via Esempio 43, Comune Test, Italia',
                    45.902040,
                    10.202040
                ),
            ];
        } elseif ('tiebreaker_nominatim_unavailable' === $mode) {
            return new WP_Error('nominatim_unavailable', 'Synthetic Nominatim unavailable.');
        } else {
            $results = [];
        }

        return [
            'headers' => [],
            'body' => wp_json_encode($results),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    if (false !== strpos((string) $url, 'anncsu-indirizzi-slim')) {
        if ('admin_only_address_like' === $mode) {
            $records = [
                asc_anncsu_exact_record('Via Esempio', '39', 'Comune Test', 45.900321, 10.200654),
            ];
        } elseif ('tiebreaker_clear_winner' === $mode) {
            $records = [
                asc_anncsu_exact_record('Via Esempio', '41', 'Comune Test', 45.901010, 10.201010),
            ];
        } elseif ('tiebreaker_ambiguous' === $mode) {
            $records = [
                asc_anncsu_exact_record('Via Esempio', '43', 'Comune Test', 45.902025, 10.202025),
            ];
        } elseif ('tiebreaker_anncsu_ambiguous' === $mode) {
            $records = [
                asc_anncsu_exact_record('Via Esempio', '43', 'Comune Test', 45.902010, 10.202010),
                asc_anncsu_exact_record('Via Esempio', '43', 'Comune Test', 45.902040, 10.202040),
            ];
        } else {
            $records = [];
        }

        return [
            'headers' => [],
            'body' => wp_json_encode($records),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    if (false !== strpos((string) $url, 'api.geoapify.com/v2/place-details')) {
        if ('ambiguous_building' === $mode) {
            $features = [
                asc_building_feature(45.900800, 10.200900),
                asc_building_feature(45.900850, 10.201000),
            ];
        } elseif (false !== strpos((string) $url, 'synthetic-building-nearer')) {
            $features = [asc_building_feature(45.901020, 10.201020)];
        } else {
            $features = [asc_building_feature(45.900800, 10.200900)];
        }

        return [
            'headers' => [],
            'body' => wp_json_encode([
                'type' => 'FeatureCollection',
                'features' => $features,
            ]),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    return $preempt;
};

add_filter('pre_http_request', $filter, 10, 3);

$settings = new Atlas_Solar_Configurator_Settings();
$fallback = new Atlas_Solar_Configurator_Geocoder($settings);
$geocoder = new Atlas_Solar_Configurator_Geoapify_Geocoder($fallback);

$request = new WP_REST_Request('GET', '/atlas-solar-configurator/v1/geocode-smart');
$request->set_param('q', 'Via Esempio 39 Comune Test');
asc_delete_geoapify_cache('Via Esempio 39 Comune Test');

$response = $geocoder->geocode($request);
asc_geoapify_assert($response instanceof WP_REST_Response, 'GEOAPIFY_RESPONSE');
$data = $response->get_data();

asc_geoapify_assert('resolved' === ($data['status'] ?? null), 'QUALITY_FILTER_RESOLVED');
asc_geoapify_assert('geoapify' === ($data['provider'] ?? null), 'GEOAPIFY_PROVIDER');
asc_geoapify_assert(1 === count($data['candidates'] ?? []), 'GENERIC_AND_DUPLICATE_RESULTS_REMOVED');

$candidate = $data['candidates'][0] ?? [];
asc_geoapify_assert(
    'building' === ($candidate['type'] ?? null),
    'RELIABLE_BUILDING_CANDIDATE_PRESERVED'
);
asc_geoapify_assert(
    'synthetic-place-39' === ($candidate['placeId'] ?? null),
    'GEOAPIFY_PLACE_ID_PRESERVED'
);
asc_geoapify_assert(
    45.900123 === ($candidate['originalLatitude'] ?? null)
        && 10.200456 === ($candidate['originalLongitude'] ?? null),
    'GEOAPIFY_ORIGINAL_COORDINATES_PRESERVED'
);
asc_geoapify_assert(
    45.9008 === ($candidate['latitude'] ?? null)
        && 10.2009 === ($candidate['longitude'] ?? null),
    'GEOAPIFY_BUILDING_COORDINATES_APPLIED'
);
asc_geoapify_assert(
    true === ($candidate['buildingSnap']['eligible'] ?? null)
        && true === ($candidate['buildingSnap']['attempted'] ?? null)
        && true === ($candidate['buildingSnap']['applied'] ?? null),
    'GEOAPIFY_BUILDING_SNAP_APPLIED'
);
asc_geoapify_assert(
    1 === ($candidate['buildingSnap']['buildingFeatureCount'] ?? null),
    'GEOAPIFY_SINGLE_BUILDING_EVIDENCE'
);
asc_geoapify_assert(
    is_numeric($candidate['buildingSnap']['distanceMeters'] ?? null)
        && (float) $candidate['buildingSnap']['distanceMeters'] > 0
        && (float) $candidate['buildingSnap']['distanceMeters'] <= 200,
    'GEOAPIFY_BUILDING_SNAP_DISTANCE_BOUNDED'
);
asc_geoapify_assert(
    'single_high_confidence_building_within_range' === ($candidate['buildingSnap']['reason'] ?? null),
    'GEOAPIFY_BUILDING_SNAP_REASON'
);

$joined_calls = implode("\n", $http_calls);
asc_geoapify_assert(
    false !== strpos($joined_calls, 'api.geoapify.com/v1/geocode/search'),
    'GEOAPIFY_SERVER_SIDE_CALLED'
);
asc_geoapify_assert(
    false !== strpos($joined_calls, 'api.geoapify.com/v2/place-details')
        && (false !== strpos($joined_calls, 'features=building')
            || false !== strpos($joined_calls, 'features%5B0%5D=building')),
    'GEOAPIFY_PLACE_DETAILS_BUILDING_CALLED'
);

$public_payload = wp_json_encode($data);
asc_geoapify_assert(
    '' === $configured_key || false === strpos((string) $public_payload, $configured_key),
    'GEOAPIFY_KEY_NOT_IN_PUBLIC_RESPONSE'
);

/* Generic locality searches must still return Geoapify locality candidates. */
$generic_query = 'Comune Test';
$mode = 'admin_only_address_like';
asc_delete_geoapify_cache($generic_query);
$request_generic = new WP_REST_Request('GET', '/atlas-solar-configurator/v1/geocode-smart');
$request_generic->set_param('q', $generic_query);
$response_generic = $geocoder->geocode($request_generic);
$data_generic = $response_generic instanceof WP_REST_Response
    ? $response_generic->get_data()
    : [];

asc_geoapify_assert(
    'geoapify' === ($data_generic['provider'] ?? null)
        && false === ($data_generic['fallbackUsed'] ?? null)
        && count($data_generic['candidates'] ?? []) > 0,
    'GENERIC_LOCALITY_SEARCH_PRESERVED'
);

/* Address-like queries with only generic administrative Geoapify results must fall back. */
$admin_only_query = 'Via Esempio 39 Comune Test';
$mode = 'admin_only_address_like';
asc_delete_geoapify_cache($admin_only_query);
$http_calls_before_admin = count($http_calls);
$response_admin_only = $geocoder->geocode($request);
$data_admin_only = $response_admin_only instanceof WP_REST_Response
    ? $response_admin_only->get_data()
    : [];
$http_calls_admin = array_slice($http_calls, $http_calls_before_admin);
$admin_candidate = $data_admin_only['candidates'][0] ?? [];

asc_geoapify_assert(
    'resolved' === ($data_admin_only['status'] ?? null)
        && 'anncsu-community-open-data' === ($data_admin_only['provider'] ?? null),
    'ADMIN_ONLY_ADDRESS_LIKE_QUERY_FALLS_BACK'
);
asc_geoapify_assert(
    true === ($data_admin_only['fallbackUsed'] ?? null)
        && 'anncsu_exact' === ($data_admin_only['fallbackMode'] ?? null),
    'ADMIN_ONLY_ADDRESS_LIKE_USES_EXACT_CIVIC_FALLBACK'
);
asc_geoapify_assert(
    'house' === ($admin_candidate['type'] ?? null)
        && 45.900321 === ($admin_candidate['latitude'] ?? null)
        && 10.200654 === ($admin_candidate['longitude'] ?? null),
    'ADMINISTRATIVE_CENTRE_NOT_RETURNED_AS_PROPERTY_POSITION'
);
asc_geoapify_assert(
    false !== strpos(implode("\n", $http_calls_admin), 'anncsu-indirizzi-slim'),
    'ADMIN_ONLY_ADDRESS_LIKE_FALLBACK_HTTP_CALLED'
);

/* Ambiguous building evidence must never move the marker. */
asc_delete_geoapify_cache('Via Esempio 39 Comune Test');
$mode = 'ambiguous_building';
$response_ambiguous_building = $geocoder->geocode($request);
$data_ambiguous_building = $response_ambiguous_building instanceof WP_REST_Response
    ? $response_ambiguous_building->get_data()
    : [];
$candidate_ambiguous_building = $data_ambiguous_building['candidates'][0] ?? [];

asc_geoapify_assert(
    45.900123 === ($candidate_ambiguous_building['latitude'] ?? null)
        && 10.200456 === ($candidate_ambiguous_building['longitude'] ?? null),
    'AMBIGUOUS_BUILDING_PRESERVES_ORIGINAL_MARKER'
);
asc_geoapify_assert(
    false === ($candidate_ambiguous_building['buildingSnap']['applied'] ?? null)
        && 2 === ($candidate_ambiguous_building['buildingSnap']['buildingFeatureCount'] ?? null)
        && 'ambiguous_building_evidence' === ($candidate_ambiguous_building['buildingSnap']['reason'] ?? null),
    'AMBIGUOUS_BUILDING_EVIDENCE_REJECTED'
);

/* Genuine ambiguity between two distinct address/building results must remain. */
$ambiguous_query = 'Via Esempio 39 Ambiguita Vera';
asc_delete_geoapify_cache($ambiguous_query);
$mode = 'true_address_ambiguity';
$request_true_ambiguity = new WP_REST_Request('GET', '/atlas-solar-configurator/v1/geocode-smart');
$request_true_ambiguity->set_param('q', $ambiguous_query);
$place_details_before = count(
    array_filter(
        $http_calls,
        static function ($url): bool {
            return false !== strpos((string) $url, 'api.geoapify.com/v2/place-details');
        }
    )
);
$response_true_ambiguity = $geocoder->geocode($request_true_ambiguity);
$data_true_ambiguity = $response_true_ambiguity instanceof WP_REST_Response
    ? $response_true_ambiguity->get_data()
    : [];
$place_details_after = count(
    array_filter(
        $http_calls,
        static function ($url): bool {
            return false !== strpos((string) $url, 'api.geoapify.com/v2/place-details');
        }
    )
);

asc_geoapify_assert(
    'ambiguous' === ($data_true_ambiguity['status'] ?? null)
        && 2 === count($data_true_ambiguity['candidates'] ?? []),
    'TRUE_ADDRESS_AMBIGUITY_PRESERVED'
);
asc_geoapify_assert(
    $place_details_before === $place_details_after,
    'BUILDING_SNAP_NOT_ATTEMPTED_ON_TRUE_ADDRESS_AMBIGUITY'
);

/* ANNCSU exact-civic evidence may select one high-confidence building only when clear. */
$clear_tiebreaker_query = 'Via Esempio 41 Comune Test';
$mode = 'tiebreaker_clear_winner';
asc_delete_geoapify_cache($clear_tiebreaker_query);
$request_clear_tiebreaker = new WP_REST_Request('GET', '/atlas-solar-configurator/v1/geocode-smart');
$request_clear_tiebreaker->set_param('q', $clear_tiebreaker_query);
$response_clear_tiebreaker = $geocoder->geocode($request_clear_tiebreaker);
$data_clear_tiebreaker = $response_clear_tiebreaker instanceof WP_REST_Response
    ? $response_clear_tiebreaker->get_data()
    : [];
$clear_candidate = $data_clear_tiebreaker['candidates'][0] ?? [];

asc_geoapify_assert(
    'resolved' === ($data_clear_tiebreaker['status'] ?? null)
        && 1 === count($data_clear_tiebreaker['candidates'] ?? []),
    'ANNCSU_TIEBREAKER_CLEAR_WINNER_RESOLVED'
);
asc_geoapify_assert(
    'synthetic-building-nearer' === ($clear_candidate['placeId'] ?? null),
    'ANNCSU_TIEBREAKER_SELECTED_NEAREST_EXACT_CIVIC_CANDIDATE'
);
asc_geoapify_assert(
    true === ($clear_candidate['anncsuTiebreaker']['attempted'] ?? null)
        && true === ($clear_candidate['anncsuTiebreaker']['applied'] ?? null)
        && 'clear_anncsu_exact_civic_winner' === ($clear_candidate['anncsuTiebreaker']['reason'] ?? null),
    'ANNCSU_TIEBREAKER_DECISION_DOCUMENTED'
);
asc_geoapify_assert(
    true === ($clear_candidate['buildingSnap']['applied'] ?? null),
    'BUILDING_SNAP_RUNS_AFTER_CLEAR_TIEBREAKER'
);

/* ANNCSU exact-civic evidence must not break a distance tie. */
$ambiguous_tiebreaker_query = 'Via Esempio 43 Comune Test';
$mode = 'tiebreaker_ambiguous';
asc_delete_geoapify_cache($ambiguous_tiebreaker_query);
$request_ambiguous_tiebreaker = new WP_REST_Request('GET', '/atlas-solar-configurator/v1/geocode-smart');
$request_ambiguous_tiebreaker->set_param('q', $ambiguous_tiebreaker_query);
$place_details_before_tie = count(
    array_filter(
        $http_calls,
        static function ($url): bool {
            return false !== strpos((string) $url, 'api.geoapify.com/v2/place-details');
        }
    )
);
$response_ambiguous_tiebreaker = $geocoder->geocode($request_ambiguous_tiebreaker);
$data_ambiguous_tiebreaker = $response_ambiguous_tiebreaker instanceof WP_REST_Response
    ? $response_ambiguous_tiebreaker->get_data()
    : [];
$place_details_after_tie = count(
    array_filter(
        $http_calls,
        static function ($url): bool {
            return false !== strpos((string) $url, 'api.geoapify.com/v2/place-details');
        }
    )
);

asc_geoapify_assert(
    'ambiguous' === ($data_ambiguous_tiebreaker['status'] ?? null)
        && 2 === count($data_ambiguous_tiebreaker['candidates'] ?? []),
    'ANNCSU_TIEBREAKER_DISTANCE_TIE_PRESERVED'
);
asc_geoapify_assert(
    'anncsu_distance_not_discriminating' === ($data_ambiguous_tiebreaker['candidates'][0]['anncsuTiebreaker']['reason'] ?? null)
        && false === ($data_ambiguous_tiebreaker['candidates'][0]['anncsuTiebreaker']['applied'] ?? null),
    'ANNCSU_TIEBREAKER_AMBIGUITY_DOCUMENTED'
);
asc_geoapify_assert(
    $place_details_before_tie === $place_details_after_tie,
    'BUILDING_SNAP_NOT_ATTEMPTED_ON_TIEBREAKER_AMBIGUITY'
);

/* ANNCSU unavailable must preserve Geoapify ambiguity. */
$mode = 'tiebreaker_anncsu_unavailable';
asc_delete_geoapify_cache($ambiguous_tiebreaker_query);
$response_tiebreaker_unavailable = $geocoder->geocode($request_ambiguous_tiebreaker);
$data_tiebreaker_unavailable = $response_tiebreaker_unavailable instanceof WP_REST_Response
    ? $response_tiebreaker_unavailable->get_data()
    : [];

asc_geoapify_assert(
    'ambiguous' === ($data_tiebreaker_unavailable['status'] ?? null)
        && 2 === count($data_tiebreaker_unavailable['candidates'] ?? []),
    'ANNCSU_UNAVAILABLE_PRESERVES_AMBIGUITY'
);
asc_geoapify_assert(
    'anncsu_exact_civic_unavailable' === ($data_tiebreaker_unavailable['candidates'][0]['anncsuTiebreaker']['reason'] ?? null)
        && false === ($data_tiebreaker_unavailable['candidates'][0]['anncsuTiebreaker']['applied'] ?? null),
    'ANNCSU_UNAVAILABLE_DOCUMENTED'
);

/* R4: street-type token boundary must work without a civic number. */
$r4_street_like_probe = \Closure::bind(
    function (string $query): bool {
        return $this->query_looks_like_address($query);
    },
    $geocoder,
    Atlas_Solar_Configurator_Geoapify_Geocoder::class
);

asc_geoapify_assert(
    is_callable($r4_street_like_probe)
        && true === $r4_street_like_probe('Via Esempio, Comune Test')
        && false === $r4_street_like_probe('Viareggio'),
    'R4_STREET_TOKEN_BOUNDARY_SAFETY'
);

asc_geoapify_assert(
    defined('ASC_VERSION') && '0.5.0' === (string) constant('ASC_VERSION'),
    'PLUGIN_VERSION_PRESERVED'
);

remove_filter('pre_http_request', $filter, 10);

fwrite(STDOUT, "CANDIDATE_QUALITY_POLICY=FILTER_GENERIC_WHEN_RELIABLE_ADDRESS_EXISTS_AND_REJECT_ADMIN_ONLY_ADDRESS_LIKE_RESULTS\n");
fwrite(STDOUT, "CANDIDATE_DEDUPLICATION=PLACE_ID_OR_EXACT_FALLBACK_KEY\n");
fwrite(STDOUT, "TRUE_ADDRESS_AMBIGUITY_PRESERVED=True\n");
fwrite(STDOUT, "ANNCSU_TIEBREAKER_POLICY=CLEAR_EXACT_CIVIC_DISTANCE_WINNER_ONLY\n");
fwrite(STDOUT, "ANNCSU_TIEBREAKER_MAX_WINNER_DISTANCE_METERS=80\n");
fwrite(STDOUT, "ANNCSU_TIEBREAKER_MIN_DISTANCE_DELTA_METERS=30\n");
fwrite(STDOUT, "ANNCSU_TIEBREAKER_MAX_WINNER_RATIO=0.60\n");
fwrite(STDOUT, "BUILDING_SNAP_POLICY=UNIQUE_HIGH_CONFIDENCE_SINGLE_BUILDING_ONLY\n");
fwrite(STDOUT, "BUILDING_SNAP_MAX_DISTANCE_METERS=200\n");
fwrite(STDOUT, "AMBIGUOUS_EVIDENCE_PRESERVES_ORIGINAL=True\n");
fwrite(STDOUT, "EXTERNAL_HTTP_EXECUTED=False\n");
fwrite(STDOUT, "R4_STREET_WITHOUT_CIVIC=True\n");
fwrite(STDOUT, "R4_VIAREGGIO_TOKEN_SAFETY=True\n");
fwrite(STDOUT, "R4_NOMINATIM_SECONDARY_TIEBREAKER=True\n");
fwrite(STDOUT, "FINAL=PASS_PUBLIC_CONFIGURATOR_GEOAPIFY_QUALITY_R4\n");
