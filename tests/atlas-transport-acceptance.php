<?php
/**
 * PLUGIN-005 R1 server-side transport acceptance.
 *
 * Execute through WP-CLI after the plugin is active:
 *   wp eval-file wp-content/plugins/atlas-solar-configurator/tests/atlas-transport-acceptance.php
 *
 * The WordPress HTTP API is intercepted with pre_http_request, therefore this
 * test never performs a real request to ATLAS.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "FAIL=WORDPRESS_NOT_LOADED\n");
    exit(1);
}

if (!class_exists('Atlas_Solar_Configurator_Atlas_Transport')) {
    fwrite(STDERR, "FAIL=TRANSPORT_CLASS_NOT_LOADED\n");
    exit(1);
}

function asc_transport_assert(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL={$label}\n");
        exit(1);
    }

    fwrite(STDOUT, "{$label}=PASS\n");
}

if (!defined('ASC_ATLAS_BASE_URL')) {
    define('ASC_ATLAS_BASE_URL', 'https://atlas.example.test');
}

if (!defined('ASC_ATLAS_BEARER_TOKEN')) {
    define('ASC_ATLAS_BEARER_TOKEN', 'plugin005-test-token');
}

$transport = new Atlas_Solar_Configurator_Atlas_Transport();

$normalized = [
    'sessionId' => 'must-not-cross-atlas-boundary',
    'address' => [
        'raw' => 'Via Esempio 39 Comune Test BS',
        'formatted' => 'Via Esempio 39, Comune Test BS',
    ],
    'propertyPosition' => [
        'latitude' => 45.000001,
        'longitude' => 10.000001,
        'confirmed' => true,
        'source' => 'map_click',
    ],
    'property' => [
        'type' => 'independent_house',
        'ownership' => 'owner',
    ],
    'consumption' => [
        'annualKwh' => 6000,
    ],
    'energyProfile' => [
        'heatPump' => true,
    ],
];

$mapped = $transport->build_preview_request($normalized);
asc_transport_assert(!is_wp_error($mapped), 'MAPPING_NOT_ERROR');
asc_transport_assert(
    $mapped === [
        'latitude' => 45.000001,
        'longitude' => 10.000001,
    ],
    'LATITUDE_LONGITUDE_ONLY'
);

$unconfirmed = $normalized;
$unconfirmed['propertyPosition']['confirmed'] = false;
$unconfirmed_result = $transport->build_preview_request($unconfirmed);
asc_transport_assert(is_wp_error($unconfirmed_result), 'UNCONFIRMED_FAIL_CLOSED');
asc_transport_assert(
    is_wp_error($unconfirmed_result)
        && 'asc_atlas_transport_position_not_confirmed' === $unconfirmed_result->get_error_code(),
    'UNCONFIRMED_ERROR_CODE'
);

$captured = [];

$filter = static function ($preempt, $args, $url) use (&$captured) {
    if (false === strpos((string) $url, '/property-intelligence/roof/click-preview')) {
        return $preempt;
    }

    $captured = [
        'url' => (string) $url,
        'args' => $args,
    ];

    return [
        'headers' => [],
        'body' => wp_json_encode(
            [
                'status' => 'PREVIEW_AVAILABLE',
                'candidate_id' => 'plugin005-r1',
                'polygon' => null,
                'area_sq_m' => 100.0,
                'roof_evidence' => null,
            ]
        ),
        'response' => [
            'code' => 200,
            'message' => 'OK',
        ],
        'cookies' => [],
        'filename' => null,
    ];
};

add_filter('pre_http_request', $filter, 10, 3);
$result = $transport->request_preview($normalized);
remove_filter('pre_http_request', $filter, 10);

asc_transport_assert(!is_wp_error($result), 'TRANSPORT_MOCK_NOT_ERROR');
asc_transport_assert('PREVIEW_AVAILABLE' === ($result['status'] ?? null), 'PUBLIC_STATUS_ACCEPTED');
asc_transport_assert(
    'https://atlas.example.test/property-intelligence/roof/click-preview' === ($captured['url'] ?? null),
    'EXACT_ATLAS_URL'
);

$headers = isset($captured['args']['headers']) && is_array($captured['args']['headers'])
    ? $captured['args']['headers']
    : [];

asc_transport_assert(
    'Bearer plugin005-test-token' === ($headers['Authorization'] ?? null),
    'SERVER_SIDE_BEARER'
);

$body = json_decode((string) ($captured['args']['body'] ?? ''), true);
asc_transport_assert(is_array($body), 'JSON_BODY');
asc_transport_assert(
    $body === [
        'latitude' => 45.000001,
        'longitude' => 10.000001,
    ],
    'WIRE_BODY_COORDINATES_ONLY'
);

foreach (['sessionId', 'address', 'property', 'consumption', 'energyProfile', 'contact', 'marketing'] as $forbidden) {
    asc_transport_assert(!array_key_exists($forbidden, $body), 'WIRE_EXCLUDES_' . strtoupper($forbidden));
}

fwrite(STDOUT, "ATLAS_HTTP_EXECUTED=False\n");
fwrite(STDOUT, "PUBLIC_BOUNDARY_CONNECTED=False\n");
fwrite(STDOUT, "FINAL=PASS_PLUGIN_005_R1_SERVER_SIDE_ADAPTER_ACCEPTANCE\n");
