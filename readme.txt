=== ATLAS Solar Lead Configurator ===
Contributors: atlas
Tags: solar, configurator, lead, photovoltaic
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Public WordPress foundation for the ATLAS solar lead configurator funnel.

== Description ==

ATLAS Solar Lead Configurator provides the first foundation of a public WordPress funnel for solar lead qualification. It is separate from ATLAS-PLATFORM and does not call real ATLAS APIs, geocoders, maps or external providers in version 0.1.0.

The plugin registers the shortcode:

[atlas_solar_configurator]

PLUGIN-001 includes a first Italian address screen, local frontend state, internal event tracking and a minimal admin page.

== Installation ==

1. Upload atlas-solar-configurator-0.1.0.zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add [atlas_solar_configurator] to a WordPress page.

== Frequently Asked Questions ==

= Does this plugin call ATLAS? =

No. ATLAS integration is disabled in version 0.1.0.

= Does this plugin calculate solar production? =

No. Technical calculations are intentionally not implemented in PLUGIN-001.

== Changelog ==

= 0.1.0 =

* Establish WordPress plugin foundation.
* Add first address step.
* Add local state and attribution capture.
* Add admin foundation settings page.
