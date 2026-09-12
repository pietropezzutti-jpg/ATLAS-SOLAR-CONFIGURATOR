# ATLAS Solar Lead Configurator

Version: 0.1.0

ATLAS Solar Lead Configurator is the public WordPress foundation for a solar lead configurator funnel. It is intentionally separate from ATLAS-PLATFORM: this plugin renders the client-facing entry point, captures the first address step and prepares state for future qualification, while ATLAS remains the internal system for CRM, Property Intelligence, Solar Resource, Solar Layout, Energy Simulation, Economic Simulation, Sales, Proposal, Contract, Signature, Customer File and Delivery.

## Architecture

The plugin follows a small WordPress class structure:

- `atlas-solar-configurator.php` boots the plugin and defines constants.
- `includes/class-plugin.php` wires assets, shortcode and admin.
- `includes/class-assets.php` registers and enqueues frontend CSS and JS only when the shortcode renders.
- `includes/class-shortcode.php` renders `[atlas_solar_configurator]`.
- `includes/class-settings.php` exposes the current foundation settings.
- `includes/class-session.php` provides an anonymous browser session id.
- `admin/class-admin.php` creates the `ATLAS Configurator` admin page.
- `templates/` contains the frontend markup.
- `public/` contains theme-independent frontend assets with the `asc-` CSS prefix.

The JavaScript frontend behaves as a mini single-page application and exposes `nextStep()`, `previousStep()`, `setState()`, `getState()` and `trackEvent()` through `window.AtlasSolarConfigurator`.

## Boundaries

PLUGIN-001 does not call ATLAS, geocoders, maps, PVGIS or other external providers. It does not calculate roof geometry, production, savings, battery results or proposals. It prepares a clean public funnel foundation for future ATLAS public APIs, where external providers must remain behind ATLAS-owned services or adapters.

AOS and development-only automation are not part of this commercial plugin.

## Installation

1. Build or use `build/atlas-solar-configurator-0.1.0.zip`.
2. In WordPress, go to Plugins > Add New > Upload Plugin.
3. Upload the ZIP and activate it.
4. Add the shortcode to a page:

```text
[atlas_solar_configurator]
```

## PLUGIN-001 State

Implemented:

- Installable WordPress plugin structure.
- Shortcode `[atlas_solar_configurator]`.
- First address screen in Italian.
- Non-empty address validation.
- Local JavaScript state persistence.
- Internal event layer for `configurator_view`, `address_started` and `address_submitted`.
- Basic marketing attribution capture from URL and browser session context.
- Admin menu `ATLAS Configurator`.
- Foundation settings: version, mode, ATLAS integration and mock mode.

## Not Implemented Yet

- Real geocoding.
- Real maps.
- ATLAS API calls.
- PVGIS or other provider calls.
- Roof geometry.
- Solar production.
- Battery scenario.
- Lead database.
- CRM handoff.
- Appointments, payments, proposal, contract or delivery workflows.

## Roadmap

1. Add a controlled ATLAS public API adapter contract.
2. Add address confirmation and map disambiguation through ATLAS-owned services.
3. Add automatic-first roof assessment with manual fallback.
4. Add preliminary solar and economic scenarios.
5. Add lead creation after the user has received useful value.
