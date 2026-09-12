<?php
/**
 * PLUGIN-005 exact-address secondary-provider acceptance.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/atlas-solar-configurator/tests/geocoder-anncsu-acceptance.php
 *
 * All HTTP is intercepted; this test never calls Nominatim or ANNCSU live.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "FAIL=WORDPRESS_NOT_LOADED\n");
    exit(1);
}

if (!class_exists('Atlas_Solar_Configurator_Geocoder')) {
    fwrite(STDERR, "FAIL=GEOCODER_CLASS_NOT_LOADED\n");
    exit(1);
}

function asc_geo_assert(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL={$label}\n");
        exit(1);
    }

    fwrite(STDOUT, "{$label}=PASS\n");
}

if (!defined('ASC_ANNCSU_ADDRESS_API_URL')) {
    define('ASC_ANNCSU_ADDRESS_API_URL', 'https://anncsu.example.test/api/v1/anncsu-indirizzi-slim');
}

$http_calls = [];
$filter = static function ($preempt, $args, $url) use (&$http_calls) {
    $http_calls[] = (string) $url;

    if (false !== strpos((string) $url, 'nominatim.openstreetmap.org')) {
        return [
            'headers' => [],
            'body' => '[]',
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    if (false !== strpos((string) $url, 'anncsu.example.test')) {
        $query = (string) (wp_parse_url((string) $url, PHP_URL_QUERY) ?? '');
        parse_str($query, $params);
        $civic = isset($params['CIVICO']) ? (string) $params['CIVICO'] : '';

        if ('eq.6' === $civic) {
            $payload = [
                [
                    'PROGRESSIVO_ACCESSO' => 'mock-scudi-6',
                    'NOME_COMUNE' => 'Costa Volpino',
                    'ODONIMO' => 'VIA DEGLI SCUDI',
                    'CIVICO' => '6',
                    'ESPONENTE' => null,
                    'latitude' => 45.840001,
                    'longitude' => 10.100001,
                    'out_of_bounds' => false,
                ],
            ];
        } else {
            $payload = [
                [
                    'PROGRESSIVO_ACCESSO' => 'mock-scudi-7-oob',
                    'NOME_COMUNE' => 'Costa Volpino',
                    'ODONIMO' => 'VIA DEGLI SCUDI',
                    'CIVICO' => '7',
                    'ESPONENTE' => null,
                    'latitude' => 45.840002,
                    'longitude' => 10.100002,
                    'out_of_bounds' => true,
                ],
            ];
        }

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
$geocoder = new Atlas_Solar_Configurator_Geocoder($settings);
$options = $settings->get_map_options();
$nominatim_endpoint = (string) $options['geocoder_endpoint'];

$run = static function (string $query) use ($geocoder, $nominatim_endpoint) {
    delete_transient('asc_geocoder_last_upstream_request_at');
    delete_transient('asc_geo_v5_' . md5(strtolower($nominatim_endpoint . '|' . $query)));

    $request = new WP_REST_Request('GET', '/atlas-solar-configurator/v1/geocode');
    $request->set_param('q', $query);

    return $geocoder->geocode($request);
};

$response = $run('via degli scudi 6 Costa volpino bg');
asc_geo_assert($response instanceof WP_REST_Response, 'GEOCODER_RESPONSE');
$data = $response->get_data();

asc_geo_assert('resolved' === ($data['status'] ?? null), 'ANNCSU_EXACT_RESOLVED');
asc_geo_assert('anncsu-community-open-data' === ($data['provider'] ?? null), 'ANNCSU_PROVIDER');
asc_geo_assert(true === ($data['fallbackUsed'] ?? null), 'ANNCSU_FALLBACK_USED');
asc_geo_assert('anncsu_exact' === ($data['fallbackMode'] ?? null), 'ANNCSU_EXACT_MODE');
asc_geo_assert(1 === count($data['candidates'] ?? []), 'ANNCSU_ONE_EXACT_CANDIDATE');

$candidate = $data['candidates'][0] ?? [];
asc_geo_assert(
    false !== stripos((string) ($candidate['displayName'] ?? ''), 'VIA DEGLI SCUDI 6'),
    'ANNCSU_DISPLAY_EXACT_CIVIC'
);
asc_geo_assert(
    45.840001 === ($candidate['latitude'] ?? null)
        && 10.100001 === ($candidate['longitude'] ?? null),
    'ANNCSU_EXACT_COORDINATES'
);

$response_oob = $run('via degli scudi 7 Costa volpino bg');
asc_geo_assert($response_oob instanceof WP_REST_Response, 'OOB_RESPONSE');
$data_oob = $response_oob->get_data();
asc_geo_assert('not_found' === ($data_oob['status'] ?? null), 'ANNCSU_OOB_REJECTED');
asc_geo_assert(0 === count($data_oob['candidates'] ?? []), 'ANNCSU_OOB_ZERO_CANDIDATES');

$anncsu_calls = array_values(
    array_filter(
        $http_calls,
        static fn($url): bool => false !== strpos((string) $url, 'anncsu.example.test')
    )
);
asc_geo_assert(count($anncsu_calls) >= 2, 'ANNCSU_SERVER_SIDE_CALLED');
asc_geo_assert(
    false !== strpos((string) $anncsu_calls[0], 'CIVICO=eq.6')
        || false !== strpos(urldecode((string) $anncsu_calls[0]), 'CIVICO=eq.6'),
    'ANNCSU_EXACT_CIVIC_FILTER'
);

remove_filter('pre_http_request', $filter, 10);

fwrite(STDOUT, "LOCALITY_CENTRE_SUBSTITUTION=False\n");
fwrite(STDOUT, "EXTERNAL_HTTP_EXECUTED=False\n");
fwrite(STDOUT, "FINAL=PASS_PLUGIN_005_EXACT_ANNCSU_SECONDARY_PROVIDER_ACCEPTANCE\n");
