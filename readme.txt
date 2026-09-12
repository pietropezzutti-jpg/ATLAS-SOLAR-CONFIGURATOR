=== ATLAS Solar Lead Configurator ===
Contributors: atlas
Tags: solar, configurator, lead, photovoltaic, map, geocoding
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public WordPress solar lead configurator with explicit property-position confirmation, ATLAS public contract and server-side transport adapter foundation.

== Description ==

ATLAS Solar Lead Configurator provides a public WordPress funnel for address resolution, property-position confirmation, property qualification, consumption profiling, a clearly labelled mock solar result and demo contact capture.

Version 0.5.0 adds the PLUGIN-005 server-side ATLAS transport adapter foundation. The exact ATLAS request is limited to confirmed latitude/longitude and uses server-side HTTP Bearer authentication. The public WordPress assessment boundary remains deliberately disconnected in this R0 release, so normal browser use still does not call ATLAS.

Shortcode:

[atlas_solar_configurator]

Public contract route:

POST /wp-json/atlas-solar-configurator/v1/assessment-contract

ATLAS transport target:

POST /property-intelligence/roof/click-preview

The adapter maps only propertyPosition.latitude and propertyPosition.longitude to the ATLAS request. Address, session id, property profile, consumption, energy profile, contact and marketing data are not forwarded through this Property Intelligence transport.

ATLAS server configuration is read only from ASC_ATLAS_BASE_URL and ASC_ATLAS_BEARER_TOKEN constants/environment variables. The bearer token is never exposed to JavaScript, HTML, localStorage or public REST responses.

The public boundary still returns BOUNDARY_READY with transmitted=false and atlasTransport=disabled until a later explicit integration gate connects it to the adapter.

Public result statuses are PREVIEW_AVAILABLE, MANUAL_FALLBACK, DISAMBIGUATION_REQUIRED, IDENTITY_NOT_RESOLVED, IDENTITY_AMBIGUOUS, RNDT_RECORD_NOT_FOUND and RNDT_RECORD_AMBIGUOUS.

The default geocoder is accessed through a same-origin WordPress REST proxy and is Nominatim-compatible. The default map uses Leaflet with OpenStreetMap tiles. Provider endpoints are configurable in WordPress admin.

== Installation ==

1. Upload a WordPress-compatible atlas-solar-configurator-0.5.0.zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add [atlas_solar_configurator] to a WordPress page.
4. Review map/geocoder and ATLAS integration status under ATLAS Configurator.
5. Configure ATLAS base URL/bearer token only in server-side deployment configuration when preparing a later live-transport gate.

== Frequently Asked Questions ==

= Does 0.5.0 call ATLAS during normal public browser use? =

No. The server-side adapter exists, but the public assessment-contract route remains disconnected and still reports transmitted=false / atlasTransport=disabled.

= What does the adapter send to ATLAS? =

Only the confirmed latitude and longitude required by RoofClickPreviewRequest.

= Where is the bearer token stored? =

It is read from server-side configuration via ASC_ATLAS_BEARER_TOKEN. It is not stored in plugin source, WordPress options, browser state or frontend configuration.

= What happens if ATLAS configuration is missing? =

The adapter fails closed with asc_atlas_transport_not_configured.

= Can contact or marketing data enter the technical transport? =

No. The PLUGIN-004 boundary rejects contact/marketing data and the PLUGIN-005 transport builds an ATLAS request containing only latitude/longitude.

= Does WordPress calculate roof geometry? =

No. WordPress confirms the property position only. Roof geometry and Property Intelligence remain ATLAS-owned.

= Can I change map/geocoder providers? =

Yes. The geocoder endpoint, tile URL and tile attribution are configurable from the ATLAS Configurator admin page.

== Changelog ==

= 0.5.0 =

* Add PLUGIN-005 server-side ATLAS transport adapter foundation.
* Implement exact RoofClickPreviewRequest mapping: latitude + longitude only.
* Target POST /property-intelligence/roof/click-preview.
* Add server-side HTTP Bearer authentication support.
* Read ATLAS base URL/token only from server configuration.
* Require HTTPS except explicit local-development hosts.
* Fail closed when transport configuration is missing or invalid.
* Validate returned ATLAS status against the PLUGIN-004 public vocabulary.
* Keep the public /assessment-contract boundary disconnected and transmitted=false.
* Keep bearer credentials out of frontend state/configuration.

= 0.4.0 =

* Add PLUGIN-004 ATLAS public API boundary foundation.
* Add POST /atlas-solar-configurator/v1/assessment-contract.
* Add contract version 1.0.
* Require explicit confirmed property coordinates before contract acceptance.
* Normalize property, consumption and energy-profile context.
* Reject contact and marketing fields from the technical assessment boundary.
* Freeze public ATLAS result status vocabulary.
* Keep ATLAS transport disabled and transmitted=false.
* Preserve the complete PLUGIN-003 geocoding/map/property-confirmation flow.

= 0.3.0 =

* Add same-origin WordPress REST geocoding proxy.
* Add configurable Nominatim-compatible geocoder endpoint.
* Add server-side geocoder cache and upstream rate limit.
* Add Leaflet 1.9.4 interactive map.
* Add configurable raster tile provider and attribution.
* Add zero/one/multiple candidate handling.
* Add draggable/click-adjustable property marker.
* Add explicit property-position confirmation before Step 2.
* Keep address latitude/longitude null until confirmation.
* Preserve the PLUGIN-002 funnel and keep ATLAS disabled.

= 0.2.0 =

* Add five-step multi-step funnel.
* Add property and ownership qualification.
* Add consumption and energy-profile inputs.
* Add deterministic mock result with explicit demo disclaimer.
* Add demo contact capture and confirmation.
* Add back navigation, reset and local-state persistence.
* Preserve campaign attribution.

= 0.1.0 =

* Establish WordPress plugin foundation.
* Add first address step.
* Add local state and attribution capture.
* Add admin foundation settings page.
