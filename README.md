# ATLAS Solar Lead Configurator

Version: 0.3.0

ATLAS Solar Lead Configurator is the public WordPress client for the ATLAS solar lead qualification funnel. It remains intentionally separate from ATLAS-PLATFORM: WordPress hosts the public experience, while ATLAS remains the future system of record for Property Intelligence, solar services, CRM, Sales, Proposal, Contract, Signature, Customer File and Delivery.

## PLUGIN-003 — Address resolution and property confirmation

Version 0.3.0 preserves the PLUGIN-002 multi-step funnel and upgrades Step 1 from plain address capture to a real location-confirmation flow:

1. Address entry.
2. Explicit, user-triggered geocoding.
3. Zero/one/multiple-candidate handling.
4. Interactive Leaflet map.
5. Candidate marker that can be moved by drag or map click.
6. Explicit property-position confirmation.
7. Property profile.
8. Consumption and energy profile.
9. Clearly labelled mock solar result.
10. Contact and privacy capture, followed by a demo confirmation screen.

The shortcode remains:

```text
[atlas_solar_configurator]
```

## Architecture

- `atlas-solar-configurator.php` boots the plugin and defines version/constants.
- `includes/class-plugin.php` wires assets, shortcode, REST geocoding and admin.
- `includes/class-settings.php` owns configurable geocoder/tile-provider settings.
- `includes/class-geocoder.php` is the same-origin WordPress geocoding proxy.
- `includes/class-assets.php` registers Leaflet plus plugin CSS/JavaScript.
- `includes/class-shortcode.php` renders the configurator.
- `includes/class-session.php` provides an anonymous browser session id.
- `admin/` contains the WordPress admin page and provider settings.
- `templates/steps/` contains the multi-step markup.
- `public/js/configurator.js` contains the mini-SPA state/navigation/map engine.
- `public/css/configurator.css` contains responsive, `asc-`-prefixed styles.

## Location model

PLUGIN-003 deliberately distinguishes a geocoding candidate from the confirmed property position.

Before confirmation:

```text
address.confirmed = false
address.latitude = null
address.longitude = null
location.propertyPosition.confirmed = false
```

The selected geocoder candidate or manually adjusted marker lives under `location.propertyPosition`.

Only after the user presses **CONFERMA POSIZIONE E CONTINUA** are the final coordinates copied to the legacy-compatible `address.latitude` / `address.longitude` fields and `address.confirmed` becomes `true`.

This implements the rule:

```text
GEOCODE = CANDIDATE POSITION
PROPERTY_POSITION = RESOLVED POSITION
```

The frontend never invents roof geometry.

## Geocoder

The browser calls a same-origin WordPress REST endpoint:

```text
/wp-json/atlas-solar-configurator/v1/geocode?q=...
```

The WordPress proxy then calls a configurable Nominatim-compatible upstream.

The default upstream is:

```text
https://nominatim.openstreetmap.org/search
```

The implementation is intentionally limited to explicit user searches:

- no autocomplete;
- maximum 5 candidates;
- Italy scope (`countrycodes=it`);
- identifying application User-Agent;
- server-side transient caching;
- upstream rate limiting;
- no contact/lead data in the geocoder request.

The endpoint can be changed from **WordPress > ATLAS Configurator** without a plugin update.

The public Nominatim service is capacity-limited and subject to its current policy:
https://operations.osmfoundation.org/policies/nominatim/

For material commercial traffic, configure a dedicated Nominatim-compatible provider or self-hosted instance.

## Map

Leaflet 1.9.4 is loaded from the official documented CDN path and the map uses a configurable raster tile URL.

Default tiles:

```text
https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

Attribution is always shown. The tile URL and attribution can be changed from the WordPress admin page.

The default OpenStreetMap tile service is best-effort and subject to:
https://operations.osmfoundation.org/policies/tiles/

For production traffic beyond moderate use, configure a dedicated commercial or self-hosted tile service.

## State and persistence

The configurator stores demo state in browser `localStorage`, including:

- raw/formatted address;
- geocoding state and candidates;
- selected/adjusted property position;
- explicit confirmation status;
- property type and ownership;
- consumption band and optional annual kWh;
- energy profile;
- mock solar result;
- contact fields;
- campaign attribution.

Legacy PLUGIN-001/PLUGIN-002 state is migrated safely. A PLUGIN-002 session that had advanced without real geographic confirmation is returned to Step 1 after upgrade to 0.3.0.

## Mock result

The Step 4 result remains deliberately labelled **DEMO / STIMA DIMOSTRATIVA**. It is deterministic and based on the selected consumption band, with optional adjustment from annual kWh.

It is still **not** a technical roof assessment.

## Boundaries

Version 0.3.0 does **not** call:

- ATLAS APIs;
- ONE CLICK;
- roof geometry services;
- PVGIS;
- CRM;
- email;
- appointment systems;
- contract/signature systems;
- external analytics providers.

No lead is persisted to WordPress or transmitted to ATLAS. Contact information remains in browser demo state.

Only the address search is sent to the configured geocoder upstream. Map tiles are loaded from the configured tile provider.

## Tracking foundation

Internal browser events now include:

- `configurator_view`
- `address_started`
- `address_submitted`
- `address_geocode_started`
- `address_geocode_resolved`
- `address_geocode_ambiguous`
- `address_geocode_not_found`
- `address_geocode_failed`
- `address_candidate_selected`
- `property_position_adjusted`
- `property_position_confirmed`
- `property_completed`
- `consumption_completed`
- `mock_result_viewed`
- `lead_form_started`
- `lead_demo_completed`
- `configurator_reset`

Campaign attribution preserves UTM parameters, `gclid`, `fbclid`, referrer and landing URL.

## Installation

Build a WordPress-compatible ZIP whose single root directory is `atlas-solar-configurator/` and whose archive entries use POSIX forward slashes.

Then:

1. WordPress > Plugins > Add New > Upload Plugin.
2. Upload the 0.3.0 ZIP.
3. Activate the plugin.
4. Add `[atlas_solar_configurator]` to a page.
5. Open **ATLAS Configurator** in WordPress admin to review provider settings.

## Next planned milestone

PLUGIN-004 may introduce the public API boundary to ATLAS after property-position confirmation.

That future boundary must remain separate from this plugin's map/geocoding responsibility and must not duplicate ATLAS business logic. ONE CLICK remains ATLAS-owned.
