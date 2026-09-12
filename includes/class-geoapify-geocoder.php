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
 * Building snap R1 is deliberately conservative: a unique Geoapify candidate
 * must have high overall confidence and building-level confidence before a
 * Place Details lookup is attempted. The marker moves only when exactly one
 * building Polygon/MultiPolygon is returned within 200 metres. Ambiguous or
 * weak evidence always preserves the original address coordinate.
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
    private const PLACE_DETAILS_ENDPOINT = 'https://api.geoapify.com/v2/place-details';
    private const CACHE_TTL = DAY_IN_SECONDS;
    private const NEGATIVE_CACHE_TTL = 5 * MINUTE_IN_SECONDS;
    private const PROVIDER = 'geoapify';
    private const BUILDING_SNAP_MAX_DISTANCE_METERS = 200.0;
    private const BUILDING_SNAP_MIN_CONFIDENCE = 0.90;
    private const BUILDING_SNAP_MIN_BUILDING_CONFIDENCE = 0.80;

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

        $cache_key = 'asc_geoapify_v3_' . md5(strtolower($query));
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
                'headers' => ['Accept' => 'application/json'],
                'user-agent' => sprintf('ATLAS-Solar-Configurator/%s (+%s)', ASC_VERSION, home_url('/')),
            ]
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
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

            $display_name = isset($item['formatted']) ? sanitize_text_field((string) $item['formatted']) : '';
            if ('' === $display_name) {
                continue;
            }

            $place_id = isset($item['place_id']) ? trim((string) $item['place_id']) : '';
            $id_source = '' !== $place_id ? $place_id : $display_name . '|' . $latitude . '|' . $longitude;
            $rank = isset($item['rank']) && is_array($item['rank']) ? $item['rank'] : [];

            $candidates[] = [
                'id' => 'geoapify-' . substr(md5($id_source), 0, 20),
                'placeId' => $place_id,
                'displayName' => $display_name,
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'originalLatitude' => (float) $latitude,
                'originalLongitude' => (float) $longitude,
                'type' => isset($item['result_type']) ? sanitize_key((string) $item['result_type']) : '',
                'category' => 'address',
                'confidence' => isset($rank['confidence']) && is_numeric($rank['confidence']) ? (float) $rank['confidence'] : null,
                'confidenceBuildingLevel' => isset($rank['confidence_building_level']) && is_numeric($rank['confidence_building_level']) ? (float) $rank['confidence_building_level'] : null,
                'matchType' => isset($rank['match_type']) ? sanitize_key((string) $rank['match_type']) : '',
                'buildingSnap' => [
                    'eligible' => false,
                    'attempted' => false,
                    'applied' => false,
                    'distanceMeters' => null,
                    'buildingFeatureCount' => null,
                    'source' => null,
                    'reason' => 'not_evaluated',
                ],
            ];
        }

        if (1 === count($candidates)) {
            $candidates[0] = $this->maybe_snap_unique_candidate_to_building($candidates[0], $api_key);
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

    private function maybe_snap_unique_candidate_to_building(array $candidate, string $api_key): array
    {
        if (!$this->candidate_is_building_snap_eligible($candidate)) {
            $candidate['buildingSnap']['reason'] = 'candidate_not_high_confidence_building_match';
            return $candidate;
        }

        $candidate['buildingSnap']['eligible'] = true;
        $candidate['buildingSnap']['attempted'] = true;

        $place_id = trim((string) ($candidate['placeId'] ?? ''));
        if ('' === $place_id) {
            $candidate['buildingSnap']['reason'] = 'place_id_missing';
            return $candidate;
        }

        $url = add_query_arg(
            [
                'id' => $place_id,
                'features' => 'building',
                'lang' => 'it',
                'apiKey' => $api_key,
            ],
            self::PLACE_DETAILS_ENDPOINT
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 8,
                'redirection' => 1,
                'headers' => ['Accept' => 'application/json'],
                'user-agent' => sprintf('ATLAS-Solar-Configurator/%s (+%s)', ASC_VERSION, home_url('/')),
            ]
        );

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            $candidate['buildingSnap']['reason'] = 'place_details_unavailable';
            return $candidate;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($decoded) || !isset($decoded['features']) || !is_array($decoded['features'])) {
            $candidate['buildingSnap']['reason'] = 'place_details_invalid';
            return $candidate;
        }

        $building_features = [];
        foreach ($decoded['features'] as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $properties = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : [];
            $feature_type = isset($properties['feature_type']) ? sanitize_key((string) $properties['feature_type']) : '';
            $geometry = isset($feature['geometry']) && is_array($feature['geometry']) ? $feature['geometry'] : [];
            $geometry_type = isset($geometry['type']) ? (string) $geometry['type'] : '';

            if ('building' === $feature_type && in_array($geometry_type, ['Polygon', 'MultiPolygon'], true)) {
                $building_features[] = $feature;
            }
        }

        $candidate['buildingSnap']['buildingFeatureCount'] = count($building_features);
        if (1 !== count($building_features)) {
            $candidate['buildingSnap']['reason'] = 0 === count($building_features)
                ? 'building_not_found'
                : 'ambiguous_building_evidence';
            return $candidate;
        }

        $building_point = $this->building_point($building_features[0]);
        if (null === $building_point) {
            $candidate['buildingSnap']['reason'] = 'building_geometry_unusable';
            return $candidate;
        }

        $distance = $this->distance_meters(
            (float) $candidate['originalLatitude'],
            (float) $candidate['originalLongitude'],
            $building_point['latitude'],
            $building_point['longitude']
        );

        $candidate['buildingSnap']['distanceMeters'] = round($distance, 1);
        $candidate['buildingSnap']['source'] = 'geoapify_place_details_building';

        if ($distance > self::BUILDING_SNAP_MAX_DISTANCE_METERS) {
            $candidate['buildingSnap']['reason'] = 'building_outside_max_distance';
            return $candidate;
        }

        $candidate['latitude'] = $building_point['latitude'];
        $candidate['longitude'] = $building_point['longitude'];
        $candidate['buildingSnap']['applied'] = true;
        $candidate['buildingSnap']['reason'] = 'single_high_confidence_building_within_range';

        return $candidate;
    }

    private function candidate_is_building_snap_eligible(array $candidate): bool
    {
        $confidence = $candidate['confidence'] ?? null;
        $building_confidence = $candidate['confidenceBuildingLevel'] ?? null;
        $match_type = (string) ($candidate['matchType'] ?? '');
        $result_type = (string) ($candidate['type'] ?? '');
        $place_id = trim((string) ($candidate['placeId'] ?? ''));

        return '' !== $place_id
            && is_numeric($confidence)
            && (float) $confidence >= self::BUILDING_SNAP_MIN_CONFIDENCE
            && is_numeric($building_confidence)
            && (float) $building_confidence >= self::BUILDING_SNAP_MIN_BUILDING_CONFIDENCE
            && in_array($match_type, ['full_match', 'match_by_building'], true)
            && in_array($result_type, ['building', 'amenity', 'address'], true);
    }

    private function building_point(array $feature): ?array
    {
        $properties = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : [];

        if (isset($properties['lat'], $properties['lon'])) {
            $latitude = filter_var($properties['lat'], FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($properties['lon'], FILTER_VALIDATE_FLOAT);
            if ($this->valid_coordinates($latitude, $longitude)) {
                return ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
            }
        }

        $geometry = isset($feature['geometry']) && is_array($feature['geometry']) ? $feature['geometry'] : [];
        $points = [];
        $this->collect_coordinate_pairs($geometry['coordinates'] ?? null, $points);
        if (0 === count($points)) {
            return null;
        }

        $latitudes = array_column($points, 'latitude');
        $longitudes = array_column($points, 'longitude');
        $latitude = (min($latitudes) + max($latitudes)) / 2;
        $longitude = (min($longitudes) + max($longitudes)) / 2;

        return $this->valid_coordinates($latitude, $longitude)
            ? ['latitude' => (float) $latitude, 'longitude' => (float) $longitude]
            : null;
    }

    private function collect_coordinate_pairs($value, array &$points): void
    {
        if (!is_array($value)) {
            return;
        }

        if (2 <= count($value) && isset($value[0], $value[1]) && is_numeric($value[0]) && is_numeric($value[1])) {
            $longitude = (float) $value[0];
            $latitude = (float) $value[1];
            if ($this->valid_coordinates($latitude, $longitude)) {
                $points[] = ['latitude' => $latitude, 'longitude' => $longitude];
            }
            return;
        }

        foreach ($value as $child) {
            $this->collect_coordinate_pairs($child, $points);
        }
    }

    private function distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth_radius = 6371000.0;
        $lat1_rad = deg2rad($lat1);
        $lat2_rad = deg2rad($lat2);
        $delta_lat = deg2rad($lat2 - $lat1);
        $delta_lon = deg2rad($lon2 - $lon1);
        $a = sin($delta_lat / 2) ** 2 + cos($lat1_rad) * cos($lat2_rad) * sin($delta_lon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
        return $earth_radius * $c;
    }

    private function api_key(): string
    {
        $value = defined('ASC_GEOAPIFY_API_KEY')
            ? (string) constant('ASC_GEOAPIFY_API_KEY')
            : (string) getenv('ASC_GEOAPIFY_API_KEY');

        $value = trim($value);
        if ('' === $value || strlen($value) > 512 || preg_match('/[\r\n]/', $value)) {
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
