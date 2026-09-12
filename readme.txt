=== ATLAS Solar Lead Configurator ===
Contributors: atlas
Tags: solar, configurator, lead, photovoltaic, map, geocoding
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public WordPress solar lead configurator with explicit property-position confirmation and ATLAS public contract foundation.

== Description ==

ATLAS Solar Lead Configurator provides a public WordPress funnel for address resolution, property-position confirmation, property qualification, consumption profiling, a clearly labelled mock solar result and demo contact capture.

Version 0.4.0 adds the PLUGIN-004 ATLAS public API boundary foundation. A same-origin WordPress REST route validates and normalizes a future Property Intelligence assessment request, but does not transmit anything to ATLAS in this release.

Shortcode:

[atlas_solar_configurator]

Contract route:

POST /wp-json/atlas-solar-configurator/v1/assessment-contract

Successful contract validation returns BOUNDARY_READY with transmitted=false and atlasTransport=disabled.

The contract accepts only technical assessment context such as confirmed property coordinates, address, property profile, consumption and energy profile. Contact and marketing data are explicitly rejected from this technical boundary.

Public result statuses reserved for future ATLAS transport include PREVIEW_AVAILABLE, MANUAL_FALLBACK, DISAMBIGUATION_REQUIRED, IDENTITY_NOT_RESOLVED, IDENTITY_AMBIGUOUS, RNDT_RECORD_NOT_FOUND and RNDT_RECORD_AMBIGUOUS.

The default geocoder is accessed through a same-origin WordPress REST proxy and is Nominatim-compatible. The default map uses Leaflet with OpenStreetMap tiles. Provider endpoints are configurable in WordPress admin.

No ATLAS API, ONE CLICK, CRM, email, appointment or contract service is called in version 0.4.0.

== Installation ==

1. Upload a WordPress-compatible atlas-solar-configurator-0.4.0.zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add [atlas_solar_configurator] to a WordPress page.
4. Review map/geocoder and ATLAS boundary status under ATLAS Configurator.

== Frequently Asked Questions ==

= Does 0.4.0 call ATLAS? =

No. PLUGIN-004 defines and validates the public contract only. ATLAS transport remains disabled.

= Why add the contract before the real transport? =

To keep WordPress and ATLAS loosely coupled and to freeze a stable, minimal public request/response vocabulary before enabling production HTTPS integration.

= Can contact or marketing data be sent through the assessment contract? =

No. The technical boundary rejects those fields. Lead/contact transmission belongs to a separate commercial integration.

= Does the plugin perform a real roof assessment? =

No. The map confirms only the property position. Roof geometry and ONE CLICK remain ATLAS-owned. The current solar result remains explicitly demo/mock.

= Can I change map/geocoder providers? =

Yes. The geocoder endpoint, tile URL and tile attribution are configurable from the ATLAS Configurator admin page.

== Changelog ==

= 0.4.0 =

* Add PLUGIN-004 ATLAS public API boundary foundation.
* Add POST /atlas-solar-configurator/v1/assessment-contract.
* Add contract version 1.0.
* Require explicit confirmed property coordinates before contract acceptance.
* Normalize property, consumption and energy-profile context.
* Reject contact and marketing fields from the technical assessment boundary.
* Freeze public ATLAS result status vocabulary.
* Keep ATLAS transport disabled and transmitted=false.
* Keep ONE CLICK and all roof intelligence ATLAS-owned.
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
* Preserve the PLUGIN-002 funnel and keep ATLAS/ONE CLICK disabled.

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
