# ATLAS Solar Lead Configurator

Version: 0.4.0

ATLAS Solar Lead Configurator is the public WordPress client for the ATLAS solar lead qualification funnel. WordPress owns the public experience; ATLAS remains the system of record for Property Intelligence, ONE CLICK, CRM/Sales, Proposal, Contract, Signature, Customer File and Delivery.

## PLUGIN-004 — ATLAS public API boundary foundation

Version 0.4.0 preserves the complete PLUGIN-003 address/map/property-confirmation flow and adds a transport-disabled public contract boundary for a future ATLAS assessment request.

The new same-origin WordPress REST route is:

```text
POST /wp-json/atlas-solar-configurator/v1/assessment-contract
```

In 0.4.0 this route performs contract validation and normalization only. It does **not** call ATLAS or ONE CLICK and returns:

```text
status = BOUNDARY_READY
transmitted = false
atlasTransport = disabled
contractVersion = 1.0
```

This lets the WordPress side and ATLAS side converge on one stable request/response contract before any production transport is enabled.

## Assessment contract v1

The accepted technical request contains only data relevant to property assessment:

```json
{
  "sessionId": "optional-browser-session-id",
  "address": {
    "raw": "Via Roma 1, Milano",
    "formatted": "1, Via Roma, ..."
  },
  "propertyPosition": {
    "latitude": 45.523692,
    "longitude": 9.330323,
    "confirmed": true,
    "source": "map_click"
  },
  "property": {
    "type": "independent_house",
    "ownership": "owner"
  },
  "consumption": {
    "annualKwh": 6000,
    "monthlyBillBand": "120_180"
  },
  "energyProfile": {
    "heatPump": false,
    "electricVehicle": false,
    "induction": false,
    "pool": false
  }
}
```

The boundary rejects technical requests containing `contact` or `marketing`. Contact/lead data belongs to the commercial flow and must not be silently mixed into the Property Intelligence request.

## Position rule

The PLUGIN-003 rule remains mandatory:

```text
GEOCODE = CANDIDATE POSITION
PROPERTY_POSITION = RESOLVED POSITION
```

A request is accepted only when the property position has been explicitly confirmed and contains valid latitude/longitude values. The frontend never invents roof geometry.

## Public result statuses

PLUGIN-004 freezes the public status vocabulary that the future ATLAS transport may return:

- `PREVIEW_AVAILABLE`
- `MANUAL_FALLBACK`
- `DISAMBIGUATION_REQUIRED`
- `IDENTITY_NOT_RESOLVED`
- `IDENTITY_AMBIGUOUS`
- `RNDT_RECORD_NOT_FOUND`
- `RNDT_RECORD_AMBIGUOUS`

These are public integration states only. Internal AOS/gate/SHA/debug states must never leak into the public WordPress UX.

## Architecture

- `atlas-solar-configurator.php` boots the plugin and defines version/constants.
- `includes/class-plugin.php` wires assets, shortcode, geocoding, ATLAS contract boundary and admin.
- `includes/class-atlas-boundary.php` validates/normalizes the ATLAS public contract without network transport.
- `includes/class-settings.php` owns configurable geocoder/tile-provider settings and status summary.
- `includes/class-geocoder.php` is the same-origin WordPress geocoding proxy.
- `includes/class-assets.php` registers Leaflet plus plugin CSS/JavaScript.
- `includes/class-shortcode.php` renders the configurator.
- `includes/class-session.php` provides an anonymous browser session id.
- `admin/` contains the WordPress admin page and provider settings.
- `templates/steps/` contains the multi-step markup.
- `public/js/configurator.js` contains the mini-SPA state/navigation/map engine.
- `public/css/configurator.css` contains responsive, `asc-`-prefixed styles.

## Existing public funnel

Version 0.4.0 keeps the already-certified PLUGIN-003 flow unchanged:

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

## Boundaries

Version 0.4.0 still does **not** call:

- ATLAS APIs;
- ONE CLICK;
- roof geometry services;
- PVGIS;
- CRM;
- email;
- appointment systems;
- contract/signature systems;
- external analytics providers.

No lead is persisted to WordPress or transmitted to ATLAS. Contact information remains in browser demo state. Only the address lookup goes to the configured geocoder and map tiles come from the configured tile provider.

## Geocoder and map

The geocoder remains a same-origin WordPress REST proxy backed by a configurable Nominatim-compatible endpoint. The default map remains Leaflet 1.9.4 with configurable raster tiles and attribution.

For production traffic beyond moderate use, configure dedicated commercial or self-hosted geocoding/tile services rather than relying indefinitely on public best-effort OpenStreetMap infrastructure.

## Installation

Build a WordPress-compatible ZIP whose single root directory is `atlas-solar-configurator/` and whose archive entries use POSIX forward slashes.

Then:

1. WordPress > Plugins > Add New > Upload Plugin.
2. Upload the 0.4.0 ZIP.
3. Activate the plugin.
4. Add `[atlas_solar_configurator]` to a page.
5. Open **ATLAS Configurator** in WordPress admin to review provider and boundary status.

## Next planned milestone

PLUGIN-005 should add the real WordPress → ATLAS HTTPS transport only after the ATLAS-side public endpoint and authentication policy are defined and certified. ONE CLICK remains ATLAS-owned; WordPress must not duplicate roof, WFS/WCS, DSM, geometry or CRM business logic.
