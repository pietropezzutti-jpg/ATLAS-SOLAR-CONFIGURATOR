=== ATLAS Solar Lead Configurator ===
Contributors: atlas
Tags: solar, configurator, lead, photovoltaic
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public WordPress multi-step demo funnel for solar lead qualification.

== Description ==

ATLAS Solar Lead Configurator provides a five-step public WordPress funnel for address capture, property qualification, consumption profiling, a clearly labelled mock solar result and demo contact capture.

The plugin remains separate from ATLAS-PLATFORM and does not call real ATLAS APIs, geocoders, maps, PVGIS or external providers in version 0.2.0.

Shortcode:

[atlas_solar_configurator]

No contact data is transmitted externally in this demo version.

== Installation ==

1. Upload a WordPress-compatible atlas-solar-configurator-0.2.0.zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add [atlas_solar_configurator] to a WordPress page.

== Frequently Asked Questions ==

= Does this plugin call ATLAS? =

No. ATLAS integration is disabled in version 0.2.0.

= Are the solar figures real? =

No. Step 4 is explicitly marked as a demo/mock estimate and is not a technical roof assessment.

= Does the plugin send lead data anywhere? =

No. Contact data remains in browser local state for demo purposes only.

== Changelog ==

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
