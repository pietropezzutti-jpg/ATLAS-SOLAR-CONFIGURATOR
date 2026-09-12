=== ATLAS Solar Lead Configurator ===
Contributors: atlas
Tags: solar, configurator, lead, photovoltaic, map, geocoding
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public WordPress solar lead configurator with explicit map/property-position confirmation.

== Description ==

ATLAS Solar Lead Configurator provides a public WordPress funnel for address resolution, property-position confirmation, property qualification, consumption profiling, a clearly labelled mock solar result and demo contact capture.

Version 0.3.0 adds real address resolution and interactive map confirmation while keeping ATLAS-PLATFORM completely separate.

Shortcode:

[atlas_solar_configurator]

The default geocoder is accessed through a same-origin WordPress REST proxy and is Nominatim-compatible. The default map uses Leaflet with OpenStreetMap tiles. Provider endpoints are configurable in WordPress admin.

No ATLAS API, ONE CLICK, CRM, email, appointment or contract service is called in version 0.3.0.

No contact data is transmitted externally. The only external request containing user-entered content is the address lookup sent to the configured geocoder provider.

== Installation ==

1. Upload a WordPress-compatible atlas-solar-configurator-0.3.0.zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add [atlas_solar_configurator] to a WordPress page.
4. Review map/geocoder provider settings under ATLAS Configurator.

== Frequently Asked Questions ==

= Does this plugin call ATLAS? =

No. ATLAS integration is disabled in version 0.3.0.

= Does the plugin perform a real roof assessment? =

No. The map confirms only the public property position. It does not create roof geometry. The solar result remains explicitly demo/mock.

= What is sent to the geocoder? =

Only the address query entered by the user. Contact details are not included.

= Does the plugin use autocomplete? =

No. Geocoding is triggered only when the user explicitly submits the address.

= Can I change map/geocoder providers? =

Yes. The geocoder endpoint, tile URL and tile attribution are configurable from the ATLAS Configurator admin page.

= Are the default OpenStreetMap services suitable for unlimited production traffic? =

No. The public Nominatim and OpenStreetMap tile services are capacity-limited and governed by their respective usage policies. Configure dedicated providers or self-hosted infrastructure for larger commercial traffic.

== Changelog ==

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
* Add migration that prevents older unconfirmed sessions from bypassing Step 1.
* Preserve the PLUGIN-002 property, consumption, mock-result, contact and tracking funnel.
* Keep ATLAS, ONE CLICK and lead transmission disabled.

= 0.2.0 =

* Add five-step multi-step funnel.
* Add property and ownership qualification.
* Add consumption and energy-profile inputs.
* Add deterministic mock result with explicit demo disclaimer.
* Add demo contact capture and confirmation.
* Add back navigation, reset and local-state persistence.
* Preserve campaign attribution.
* Correct address semantics so an entered address is not treated as a confirmed property.
* Add migration from PLUGIN-001 local state.

= 0.1.0 =

* Establish WordPress plugin foundation.
* Add first address step.
* Add local state and attribution capture.
* Add admin foundation settings page.
