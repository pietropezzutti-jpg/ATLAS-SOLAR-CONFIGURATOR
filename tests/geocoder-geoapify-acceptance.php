<?php
/**
 * Geoapify candidate-quality R2 + building-snap R1 acceptance.
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
    return [
        'place_id' => $place_id,
        'formatted' => $formatted,
        'lat' => $lat,
        'lon' => $lon,
        'result_type' => 'building',
        'rank' => [
            'confidence' => 0.97,
            'confidence_building_level' => 0.91,
            'match_type' => 'full_match',
        ],
    ];
}

$mode = 'quality_filter';
$http_calls = [];
$filter = static function ($preempt, $args, $url) use (&$http_calls, &$mode) {
    $http_calls[] = (string) $url;

    if (false !== strpos((string) $url, 'api.geoapify.com/v1/geocode/search')) {
        if ('true_address_ambiguity' === $mode) {
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

    if (false !== strpos((string) $url, 'api.geoapify.com/v2/place-details')) {
        $features = 'ambiguous_building' === $mode
            ? [
                asc_building_feature(45.900800, 10.200900),
                asc_building_feature(45.900850, 10.201000),
            ]
            : [asc_building_feature(45.900800, 10.200900)];

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

/* Ambiguous building evidence must never move the marker. */
delete_transient('asc_geoapify_v4_' . md5(strtolower('Via Esempio 39 Comune Test')));
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
delete_transient('asc_geoapify_v4_' . md5(strtolower($ambiguous_query)));
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

remove_filter('pre_http_request', $filter, 10);

fwrite(STDOUT, "CANDIDATE_QUALITY_POLICY=FILTER_GENERIC_WHEN_RELIABLE_ADDRESS_EXISTS\n");
fwrite(STDOUT, "CANDIDATE_DEDUPLICATION=PLACE_ID_OR_EXACT_FALLBACK_KEY\n");
fwrite(STDOUT, "TRUE_ADDRESS_AMBIGUITY_PRESERVED=True\n");
fwrite(STDOUT, "BUILDING_SNAP_POLICY=UNIQUE_HIGH_CONFIDENCE_SINGLE_BUILDING_ONLY\n");
fwrite(STDOUT, "BUILDING_SNAP_MAX_DISTANCE_METERS=200\n");
fwrite(STDOUT, "AMBIGUOUS_EVIDENCE_PRESERVES_ORIGINAL=True\n");
fwrite(STDOUT, "EXTERNAL_HTTP_EXECUTED=False\n");
fwrite(STDOUT, "FINAL=PASS_PUBLIC_CONFIGURATOR_GEOAPIFY_CANDIDATE_QUALITY_R2\n");
