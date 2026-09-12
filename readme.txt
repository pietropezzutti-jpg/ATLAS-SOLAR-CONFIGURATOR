=== ATLAS Solar Lead Configurator ===
Contributors: atlas
Tags: solar, configurator, lead, photovoltaic, map, geocoding
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public WordPress solar lead configurator with explicit property-position confirmation, national orthophoto and server-side geocoding/provider integration.

== Description ==

ATLAS Solar Lead Configurator provides a public WordPress funnel for address resolution, property-position confirmation, property qualification, consumption profiling, a clearly labelled mock solar result and demo contact capture.

Version 0.5.0 includes the PLUGIN-005 server-side ATLAS transport adapter foundation. The exact ATLAS request is limited to confirmed latitude/longitude and uses server-side HTTP Bearer authentication. The public WordPress assessment boundary remains deliberately disconnected in this R1 acceptance phase, so normal browser use still does not call ATLAS.

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

Address lookup now uses a server-side Geoapify-first route when ASC_GEOAPIFY_API_KEY is configured. The key remains server-side and is never emitted to frontend configuration or REST responses. Searches are restricted to Italy. If Geoapify is not configured, returns no candidates or is temporarily unavailable, the existing Nominatim-compatible geocoder and strict ANNCSU exact-civic fallback remain active.

The legacy Nominatim-compatible geocoder still performs free-form and structured address lookup. If both fail for an Italian civic address, the server may perform an exact fallback against ANNCSU-derived open data. The default fallback endpoint is the community-operated mirror at developers.coseerobe.it/api/v1/anncsu-indirizzi-slim; it is not an official Agenzia delle Entrate API. It can be overridden with ASC_ANNCSU_ADDRESS_API_URL or disabled by defining that constant as an empty string.

The ANNCSU fallback accepts only records that match municipality, street and civic, include valid coordinates and are not flagged out_of_bounds. It never replaces an unresolved address with a municipality or street centroid. Any candidate must still be confirmed by the user on the map before propertyPosition becomes valid.

The default map uses Leaflet with OpenStreetMap tiles and a national MASE / Geoportale Nazionale orthophoto layer. Address provider and imagery are independent: changing geocoder does not change the map or the national aerial imagery.

== Installation ==

1. Upload a WordPress-compatible atlas-solar-configurator-0.5.0.zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add [atlas_solar_configurator] to a WordPress page.
4. Review map/geocoder and integration status under ATLAS Configurator.
5. For Geoapify-first address lookup, set ASC_GEOAPIFY_API_KEY only in server-side configuration. Do not put the key in JavaScript or public HTML.
6. Optionally set ASC_ANNCSU_ADDRESS_API_URL server-side to override or disable the community ANNCSU open-data mirror.
7. Configure ATLAS base URL/bearer token only in server-side deployment configuration when preparing a later live-transport gate.

== Frequently Asked Questions ==

= Does the public configurator require Google Maps? =

No. The map uses Leaflet, OpenStreetMap and the configured national orthophoto. Geoapify, when enabled, is used only for server-side address lookup.

= Where is the Geoapify key stored? =

It is read from the server-side ASC_GEOAPIFY_API_KEY constant or environment variable. It is not stored in frontend JavaScript, HTML, localStorage or public REST responses.

= What happens if Geoapify is not configured or finds nothing? =

The request falls back to the existing Nominatim-compatible and ANNCSU exact-civic address resolution flow.

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

Yes. The legacy geocoder endpoint, tile URL and tile attribution are configurable from the ATLAS Configurator admin page. Geoapify and the exact-civic ANNCSU fallback are server-side integrations and do not expose credentials to the browser.

= Is the default ANNCSU fallback endpoint official? =

No. It is a community-operated REST mirror over ANNCSU open data. The adapter is deliberately configurable so a controlled or official source can replace it without changing frontend behavior.

== Changelog ==

= 0.5.0 =

* Add optional server-side Geoapify-first Italian address lookup via ASC_GEOAPIFY_API_KEY.
* Keep the Geoapify key outside frontend JavaScript, HTML and public REST responses.
* Preserve Nominatim + ANNCSU as automatic fallback when Geoapify is unavailable or has no result.
* Preserve Leaflet/OpenStreetMap and the national MASE / Geoportale Nazionale orthophoto independently from address search.
* Add PLUGIN-005 server-side ATLAS transport adapter foundation and R1 acceptance harness.
* Implement exact RoofClickPreviewRequest mapping: latitude + longitude only.
* Target POST /property-intelligence/roof/click-preview.
* Add server-side HTTP Bearer authentication support.
* Read ATLAS base URL/token only from server configuration.
* Require HTTPS except explicit local-development hosts.
* Fail closed when transport configuration is missing or invalid.
* Validate returned ATLAS status against the PLUGIN-004 public vocabulary.
* Keep the public /assessment-contract boundary disconnected and transmitted=false.
* Keep bearer credentials out of frontend state/configuration.
* Remove municipality-centre substitution from address resolution.
* Add exact structured Nominatim retry for unresolved civic addresses.
* Add strict server-side ANNCSU open-data exact-civic fallback with configurable community mirror.
* Reject ANNCSU records with wrong municipality/street/civic, missing coordinates or out_of_bounds=true.
* Keep property coordinates unconfirmed until explicit map confirmation.

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
