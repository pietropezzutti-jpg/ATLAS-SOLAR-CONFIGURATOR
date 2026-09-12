<?php
/**
 * Geoapify-first geocoder acceptance.
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

$http_calls = [];
$filter = static function ($preempt, $args, $url) use (&$http_calls) {
    $http_calls[] = (string) $url;

    if (false !== strpos((string) $url, 'api.geoapify.com/v1/geocode/search')) {
        $payload = [
            'results' => [
                [
                    'place_id' => 'synthetic-place-39',
                    'formatted' => 'Via Esempio 39, 25000 Comune Test, Italia',
                    'lat' => 45.900123,
                    'lon' => 10.200456,
                    'result_type' => 'building',
                ],
            ],
        ];

        return [
            'headers' => [],
            'body' => wp_json_encode($payload),
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

asc_geoapify_assert('resolved' === ($data['status'] ?? null), 'GEOAPIFY_RESOLVED');
asc_geoapify_assert('geoapify' === ($data['provider'] ?? null), 'GEOAPIFY_PROVIDER');
asc_geoapify_assert(1 === count($data['candidates'] ?? []), 'GEOAPIFY_ONE_CANDIDATE');

$candidate = $data['candidates'][0] ?? [];
asc_geoapify_assert(
    false !== stripos((string) ($candidate['displayName'] ?? ''), 'Via Esempio 39'),
    'GEOAPIFY_DISPLAY_ADDRESS'
);
asc_geoapify_assert(
    45.900123 === ($candidate['latitude'] ?? null)
        && 10.200456 === ($candidate['longitude'] ?? null),
    'GEOAPIFY_COORDINATES'
);

$joined_calls = implode("\n", $http_calls);
asc_geoapify_assert(
    false !== strpos($joined_calls, 'api.geoapify.com/v1/geocode/search'),
    'GEOAPIFY_SERVER_SIDE_CALLED'
);
asc_geoapify_assert(
    false !== strpos($joined_calls, 'filter=countrycode%3Ait')
        || false !== strpos($joined_calls, 'filter=countrycode:it'),
    'GEOAPIFY_ITALY_FILTER'
);

$public_payload = wp_json_encode($data);
asc_geoapify_assert(
    '' === $configured_key || false === strpos((string) $public_payload, $configured_key),
    'GEOAPIFY_KEY_NOT_IN_PUBLIC_RESPONSE'
);

remove_filter('pre_http_request', $filter, 10);

fwrite(STDOUT, "EXTERNAL_HTTP_EXECUTED=False\n");
fwrite(STDOUT, "FINAL=PASS_PUBLIC_CONFIGURATOR_GEOAPIFY_PRIMARY_ACCEPTANCE\n");
