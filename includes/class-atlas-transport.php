<?php
/**
 * Server-side ATLAS transport adapter foundation.
 *
 * PLUGIN-005 maps the already-normalized public assessment contract to the
 * minimal ATLAS Property Intelligence request. The adapter is intentionally
 * not connected to the public boundary in this release: no browser request can
 * trigger ATLAS transport until a later, separately certified integration gate.
 *
 * @package AtlasSolarConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Atlas_Solar_Configurator_Atlas_Transport
{
    public const ATLAS_PATH = '/property-intelligence/roof/click-preview';

    private const BASE_URL_KEY = 'ASC_ATLAS_BASE_URL';
    private const BEARER_TOKEN_KEY = 'ASC_ATLAS_BEARER_TOKEN';
    private const TIMEOUT_SECONDS = 20;

    /**
     * Return a non-secret transport status for diagnostics/admin summary.
     */
    public function configuration_status(): array
    {
        $base_url = $this->base_url();
        $token = $this->bearer_token();

        return [
            'configured' => null !== $base_url && null !== $token,
            'baseUrlConfigured' => null !== $base_url,
            'bearerTokenConfigured' => null !== $token,
            'publicBoundaryConnected' => false,
        ];
    }

    /**
     * Map the rich normalized WordPress contract to the exact ATLAS request.
     *
     * Only confirmed coordinates cross this technical transport boundary.
     * Address, session, property profile, consumption, energy profile,
     * contact and marketing data are deliberately not forwarded.
     *
     * @param array $normalized_contract PLUGIN-004 normalized request.
     * @return array|WP_Error
     */
    public function build_preview_request(array $normalized_contract)
    {
        $position = isset($normalized_contract['propertyPosition'])
            && is_array($normalized_contract['propertyPosition'])
            ? $normalized_contract['propertyPosition']
            : [];

        if (!rest_sanitize_boolean($position['confirmed'] ?? false)) {
            return new WP_Error(
                'asc_atlas_transport_position_not_confirmed',
                __('La posizione dell’immobile deve essere confermata prima del trasporto ATLAS.', 'atlas-solar-configurator'),
                ['status' => 400]
            );
        }

        $latitude = $this->coordinate($position['latitude'] ?? null, -90.0, 90.0);
        $longitude = $this->coordinate($position['longitude'] ?? null, -180.0, 180.0);

        if (null === $latitude || null === $longitude) {
            return new WP_Error(
                'asc_atlas_transport_coordinates_invalid',
                __('Coordinate immobile non valide per il trasporto ATLAS.', 'atlas-solar-configurator'),
                ['status' => 400]
            );
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Execute the server-side ATLAS request.
     *
     * This method is available for the next integration gate but is not wired
     * to any public WordPress route in PLUGIN-005 R0.
     *
     * @param array $normalized_contract PLUGIN-004 normalized request.
     * @return array|WP_Error
     */
    public function request_preview(array $normalized_contract)
    {
        $base_url = $this->base_url();
        $token = $this->bearer_token();

        if (null === $base_url || null === $token) {
            return new WP_Error(
                'asc_atlas_transport_not_configured',
                __('Trasporto ATLAS non configurato.', 'atlas-solar-configurator'),
                ['status' => 503]
            );
        }

        $body = $this->build_preview_request($normalized_contract);
        if (is_wp_error($body)) {
            return $body;
        }

        $endpoint = rtrim($base_url, '/') . self::ATLAS_PATH;

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout' => self::TIMEOUT_SECONDS,
                'redirection' => 0,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body' => wp_json_encode($body),
                'data_format' => 'body',
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'asc_atlas_transport_unavailable',
                __('ATLAS non è raggiungibile dal server WordPress.', 'atlas-solar-configurator'),
                ['status' => 502]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw_body, true);

        if (200 !== $status_code) {
            return new WP_Error(
                'asc_atlas_transport_http_error',
                __('ATLAS ha rifiutato la richiesta tecnica.', 'atlas-solar-configurator'),
                [
                    'status' => 502,
                    'upstreamStatus' => $status_code,
                ]
            );
        }

        if (!is_array($decoded) || !isset($decoded['status'])) {
            return new WP_Error(
                'asc_atlas_transport_invalid_response',
                __('Risposta ATLAS non valida.', 'atlas-solar-configurator'),
                ['status' => 502]
            );
        }

        $allowed_statuses = Atlas_Solar_Configurator_Atlas_Boundary::public_result_statuses();
        if (!in_array($decoded['status'], $allowed_statuses, true)) {
            return new WP_Error(
                'asc_atlas_transport_unknown_status',
                __('ATLAS ha restituito uno stato non riconosciuto.', 'atlas-solar-configurator'),
                ['status' => 502]
            );
        }

        return $decoded;
    }

    private function base_url(): ?string
    {
        $raw = $this->server_value(self::BASE_URL_KEY);
        if (null === $raw) {
            return null;
        }

        $candidate = trim($raw);
        if ('' === $candidate) {
            return null;
        }

        $parts = wp_parse_url($candidate);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if ('https' !== $scheme && !('http' === $scheme && $this->is_local_development_host($host))) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $sanitized = esc_url_raw($candidate, ['http', 'https']);

        return $sanitized ? rtrim($sanitized, '/') : null;
    }

    private function bearer_token(): ?string
    {
        $raw = $this->server_value(self::BEARER_TOKEN_KEY);
        if (null === $raw) {
            return null;
        }

        $token = trim($raw);
        if ('' === $token || strlen($token) > 4096 || preg_match('/[\r\n]/', $token)) {
            return null;
        }

        return $token;
    }

    private function server_value(string $key): ?string
    {
        if (defined($key)) {
            $value = constant($key);
            return is_scalar($value) ? (string) $value : null;
        }

        $value = getenv($key);

        return false === $value ? null : (string) $value;
    }

    private function coordinate($value, float $minimum, float $maximum): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            return null;
        }

        return $number;
    }

    private function is_local_development_host(string $host): bool
    {
        return in_array(
            $host,
            [
                'localhost',
                '127.0.0.1',
                '::1',
                'host.docker.internal',
                'atlas-api',
            ],
            true
        );
    }
}
