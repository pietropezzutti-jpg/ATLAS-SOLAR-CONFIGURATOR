# ATLAS Solar Lead Configurator

Version: 0.5.0

ATLAS Solar Lead Configurator is the public WordPress client for the ATLAS solar lead qualification funnel. WordPress owns the public experience; ATLAS remains the system of record for Property Intelligence, CRM/Sales, Proposal, Contract, Signature, Customer File and Delivery.

## PLUGIN-005 — server-side ATLAS transport adapter foundation

Version 0.5.0 preserves the certified PLUGIN-003 address/map flow and the PLUGIN-004 public assessment contract. It adds a server-side transport adapter that knows the exact ATLAS Property Intelligence request/response contract, but the public WordPress REST boundary is intentionally **not connected** to that transport yet.

The ATLAS endpoint discovered from the running FastAPI OpenAPI document is:

```text
POST /property-intelligence/roof/click-preview
Authorization: Bearer <server-side-token>
```

Its request is deliberately minimal:

```json
{
  "latitude": 45.523692,
  "longitude": 9.330323
}
```

The adapter derives those two values only from an already-normalized PLUGIN-004 contract whose property position is explicitly confirmed. Address, browser session, property profile, consumption, energy profile, contact and marketing data are not forwarded through this Property Intelligence request.

## Public WordPress contract remains disconnected

The same-origin WordPress route remains:

```text
POST /wp-json/atlas-solar-configurator/v1/assessment-contract
```

In PLUGIN-005 R0 it still validates and normalizes only. It continues to return:

```text
status = BOUNDARY_READY
transmitted = false
atlasTransport = disabled
contractVersion = 1.0
```

This separation is intentional. The adapter is implemented and testable server-side before any public browser flow is allowed to trigger an ATLAS request.

## Server-side transport configuration

ATLAS transport configuration is read only from server-side constants or environment variables:

```text
ASC_ATLAS_BASE_URL
ASC_ATLAS_BEARER_TOKEN
```

The bearer token is never added to `ASC_CONFIG`, JavaScript, HTML, localStorage, WordPress options or public REST responses.

Production ATLAS base URLs must use HTTPS. Plain HTTP is accepted only for explicit local-development hosts such as `localhost`, `127.0.0.1`, `host.docker.internal` and the local Docker service name `atlas-api`.

If either the base URL or bearer token is missing/invalid, the adapter fails closed with `asc_atlas_transport_not_configured`.

## Exact transport mapping

The normalized WordPress contract is richer than the ATLAS roof-preview request. The adapter maps only:

| WordPress normalized field | ATLAS request field |
| --- | --- |
| `propertyPosition.latitude` | `latitude` |
| `propertyPosition.longitude` | `longitude` |

No other public configurator field is transmitted by this adapter.

## ATLAS response vocabulary

The adapter accepts only the public statuses already frozen by PLUGIN-004:

- `PREVIEW_AVAILABLE`
- `MANUAL_FALLBACK`
- `DISAMBIGUATION_REQUIRED`
- `IDENTITY_NOT_RESOLVED`
- `IDENTITY_AMBIGUOUS`
- `RNDT_RECORD_NOT_FOUND`
- `RNDT_RECORD_AMBIGUOUS`

The ATLAS response may also contain `candidate_id`, `polygon`, `area_sq_m` and `roof_evidence`. WordPress does not calculate or invent those values.

## Architecture

- `atlas-solar-configurator.php` boots the plugin and defines version/constants.
- `includes/class-plugin.php` wires assets, shortcode, geocoding, public contract boundary and the disconnected transport adapter foundation.
- `includes/class-atlas-boundary.php` validates/normalizes the public WordPress contract.
- `includes/class-atlas-transport.php` performs the exact server-side WordPress → ATLAS mapping and contains the future authenticated HTTP transport method.
- `includes/class-settings.php` owns configurable geocoder/tile-provider settings and non-secret status summary.
- `includes/class-geocoder.php` is the same-origin WordPress geocoding proxy.
- `includes/class-assets.php` registers Leaflet plus plugin CSS/JavaScript.
- `includes/class-shortcode.php` renders the configurator.
- `includes/class-session.php` provides an anonymous browser session id.
- `admin/` contains the WordPress admin page and provider/status presentation.
- `templates/steps/` contains the multi-step markup.
- `public/js/configurator.js` contains the mini-SPA state/navigation/map engine.
- `public/css/configurator.css` contains responsive, `asc-`-prefixed styles.

## Existing public funnel

Version 0.5.0 keeps the previously certified flow unchanged:

1. Address entry.
2. Explicit geocoding.
3. Zero/one/multiple candidate handling.
4. Leaflet map.
5. Marker adjustment by drag or map click.
6. Explicit property-position confirmation.
7. Property profile.
8. Consumption and energy profile.
9. Clearly labelled mock solar result.
10. Contact and privacy capture followed by demo confirmation.

Shortcode:

```text
[atlas_solar_configurator]
```

## Position rule

The rule remains:

```text
GEOCODE = CANDIDATE POSITION
PROPERTY_POSITION = RESOLVED POSITION
```

Only an explicitly confirmed property position can be mapped to an ATLAS request.

## Boundaries

PLUGIN-005 R0 does not expose a new public transport route and does not connect `/assessment-contract` to ATLAS. Therefore normal browser use still performs no ATLAS request.

The plugin does not duplicate ATLAS roof geometry, WFS/WCS, DSM, CRM, proposal, contract or signature business logic. Contact/lead transmission also remains disabled.

## Geocoder and map

The geocoder remains a same-origin WordPress REST proxy backed by a configurable Nominatim-compatible endpoint. The default map remains Leaflet 1.9.4 with configurable raster tiles and attribution.

For production traffic beyond moderate use, configure dedicated commercial or self-hosted geocoding/tile services rather than relying indefinitely on public best-effort OpenStreetMap infrastructure.

## Installation

Build a WordPress-compatible ZIP whose single root directory is `atlas-solar-configurator/` and whose archive entries use POSIX forward slashes.

Then:

1. WordPress > Plugins > Add New > Upload Plugin.
2. Upload the 0.5.0 ZIP.
3. Activate the plugin.
4. Add `[atlas_solar_configurator]` to a page.
5. Open **ATLAS Configurator** in WordPress admin to review provider and integration status.
6. Configure `ASC_ATLAS_BASE_URL` and `ASC_ATLAS_BEARER_TOKEN` only in server-side deployment configuration when preparing the later live-transport gate.

## Next planned milestone

PLUGIN-005 R1 should certify the adapter independently: exact coordinate-only mapping, fail-closed configuration, bearer secrecy and mocked/server-side transport behavior. Only a later explicit gate may connect the public assessment boundary to the adapter.
