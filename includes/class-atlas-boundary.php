<?php
/**
 * Public ATLAS assessment contract boundary.
 *
 * PLUGIN-004 validates and normalizes the request that a future transport
 * adapter will send to ATLAS after the user confirms the property position.
 * No ATLAS network call is performed in this release.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Atlas_Boundary
{
    public const CONTRACT_VERSION = '1.0';

    private const ROUTE_NAMESPACE = 'atlas-solar-configurator/v1';
    private const ROUTE = '/assessment-contract';

    private const PROPERTY_TYPES = [
        'independent_house',
        'semi_detached',
        'condominium',
        'business',
        'other',
    ];

    private const OWNERSHIP_TYPES = [
        'owner',
        'non_owner',
    ];

    private const BILL_BANDS = [
        'lt_70',
        '70_120',
        '120_180',
        '180_300',
        'gt_300',
    ];

    private const PUBLIC_RESULT_STATUSES = [
        'PREVIEW_AVAILABLE',
        'MANUAL_FALLBACK',
        'DISAMBIGUATION_REQUIRED',
        'IDENTITY_NOT_RESOLVED',
        'IDENTITY_AMBIGUOUS',
        'RNDT_RECORD_NOT_FOUND',
        'RNDT_RECORD_AMBIGUOUS',
    ];

    public function register_routes(): void
    {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            self::ROUTE,
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'validate_contract'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function validate_contract(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();

        if (!is_array($payload)) {
            return $this->error(
                'asc_atlas_boundary_invalid_json',
                __('Payload JSON non valido.', 'atlas-solar-configurator')
            );
        }

        foreach (['contact', 'marketing'] as $forbidden) {
            if (array_key_exists($forbidden, $payload)) {
                return $this->error(
                    'asc_atlas_boundary_forbidden_fields',
                    __('Il contratto tecnico ATLAS non accetta dati di contatto o marketing.', 'atlas-solar-configurator')
                );
            }
        }

        $normalized = $this->normalize_request($payload);

        if (is_wp_error($normalized)) {
            return $normalized;
        }

        $response = new WP_REST_Response(
            [
                'status' => 'BOUNDARY_READY',
                'contractVersion' => self::CONTRACT_VERSION,
                'requestId' => wp_generate_uuid4(),
                'transmitted' => false,
                'atlasTransport' => 'disabled',
                'request' => $normalized,
                'allowedResultStatuses' => self::PUBLIC_RESULT_STATUSES,
            ],
            200
        );

        $response->header('Cache-Control', 'no-store');

        return $response;
    }

    public static function public_result_statuses(): array
    {
        return self::PUBLIC_RESULT_STATUSES;
    }

    private function normalize_request(array $payload)
    {
        $address = isset($payload['address']) && is_array($payload['address'])
            ? $payload['address']
            : [];

        $position = isset($payload['propertyPosition']) && is_array($payload['propertyPosition'])
            ? $payload['propertyPosition']
            : [];

        $raw_address = $this->clean_text($address['raw'] ?? '', 300);
        $formatted_address = $this->clean_text($address['formatted'] ?? '', 500);

        if ('' === $raw_address && '' === $formatted_address) {
            return $this->error(
                'asc_atlas_boundary_address_required',
                __('Indirizzo immobile mancante.', 'atlas-solar-configurator')
            );
        }

        if (!rest_sanitize_boolean($position['confirmed'] ?? false)) {
            return $this->error(
                'asc_atlas_boundary_position_not_confirmed',
                __('La posizione dell’immobile deve essere confermata prima della richiesta ATLAS.', 'atlas-solar-configurator')
            );
        }

        $latitude = $this->coordinate($position['latitude'] ?? null, -90, 90);
        $longitude = $this->coordinate($position['longitude'] ?? null, -180, 180);

        if (null === $latitude || null === $longitude) {
            return $this->error(
                'asc_atlas_boundary_coordinates_invalid',
                __('Coordinate immobile non valide.', 'atlas-solar-configurator')
            );
        }

        $property = isset($payload['property']) && is_array($payload['property'])
            ? $payload['property']
            : [];

        $property_type = $this->enum_or_null(
            $property['type'] ?? null,
            self::PROPERTY_TYPES
        );

        $ownership = $this->enum_or_null(
            $property['ownership'] ?? null,
            self::OWNERSHIP_TYPES
        );

        if (isset($property['type']) && null === $property_type) {
            return $this->error(
                'asc_atlas_boundary_property_type_invalid',
                __('Tipo immobile non valido.', 'atlas-solar-configurator')
            );
        }

        if (isset($property['ownership']) && null === $ownership) {
            return $this->error(
                'asc_atlas_boundary_ownership_invalid',
                __('Titolarità immobile non valida.', 'atlas-solar-configurator')
            );
        }

        $consumption = isset($payload['consumption']) && is_array($payload['consumption'])
            ? $payload['consumption']
            : [];

        $annual_kwh = null;
        if (array_key_exists('annualKwh', $consumption) && null !== $consumption['annualKwh'] && '' !== $consumption['annualKwh']) {
            if (!is_numeric($consumption['annualKwh']) || (float) $consumption['annualKwh'] < 0) {
                return $this->error(
                    'asc_atlas_boundary_annual_kwh_invalid',
                    __('Consumo annuo non valido.', 'atlas-solar-configurator')
                );
            }

            $annual_kwh = (float) $consumption['annualKwh'];
        }

        $monthly_bill_band = $this->enum_or_null(
            $consumption['monthlyBillBand'] ?? null,
            self::BILL_BANDS
        );

        if (isset($consumption['monthlyBillBand']) && null === $monthly_bill_band) {
            return $this->error(
                'asc_atlas_boundary_bill_band_invalid',
                __('Fascia di spesa non valida.', 'atlas-solar-configurator')
            );
        }

        $energy = isset($payload['energyProfile']) && is_array($payload['energyProfile'])
            ? $payload['energyProfile']
            : [];

        return [
            'contractVersion' => self::CONTRACT_VERSION,
            'source' => 'wordpress',
            'sessionId' => $this->clean_text($payload['sessionId'] ?? '', 100),
            'address' => [
                'raw' => $raw_address,
                'formatted' => '' !== $formatted_address ? $formatted_address : $raw_address,
            ],
            'propertyPosition' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'confirmed' => true,
                'source' => $this->clean_text($position['source'] ?? '', 80),
            ],
            'property' => [
                'type' => $property_type,
                'ownership' => $ownership,
            ],
            'consumption' => [
                'annualKwh' => $annual_kwh,
                'monthlyBillBand' => $monthly_bill_band,
            ],
            'energyProfile' => [
                'heatPump' => rest_sanitize_boolean($energy['heatPump'] ?? false),
                'electricVehicle' => rest_sanitize_boolean($energy['electricVehicle'] ?? false),
                'induction' => rest_sanitize_boolean($energy['induction'] ?? false),
                'pool' => rest_sanitize_boolean($energy['pool'] ?? false),
            ],
        ];
    }

    private function coordinate($value, float $minimum, float $maximum): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < $minimum || $number > $maximum) {
            return null;
        }

        return $number;
    }

    private function enum_or_null($value, array $allowed): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $candidate = sanitize_key((string) $value);

        return in_array($candidate, $allowed, true) ? $candidate : null;
    }

    private function clean_text($value, int $max_length): string
    {
        $candidate = sanitize_text_field((string) $value);

        if (strlen($candidate) > $max_length) {
            $candidate = substr($candidate, 0, $max_length);
        }

        return $candidate;
    }

    private function error(string $code, string $message): WP_Error
    {
        return new WP_Error(
            $code,
            $message,
            ['status' => 400]
        );
    }
}
