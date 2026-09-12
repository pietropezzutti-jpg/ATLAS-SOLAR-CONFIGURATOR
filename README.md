# ATLAS Solar Lead Configurator

Version: 0.2.0

ATLAS Solar Lead Configurator is the public WordPress client for a solar lead qualification funnel. It remains intentionally separate from ATLAS-PLATFORM: WordPress hosts the public experience, while ATLAS remains the future system of record for Property Intelligence, solar services, CRM, Sales, Proposal, Contract, Signature, Customer File and Delivery.

## PLUGIN-002 — Multi-step funnel

Version 0.2.0 implements a five-step client-side demo funnel:

1. Address.
2. Property profile.
3. Consumption and energy profile.
4. Clearly labelled mock solar result.
5. Contact and privacy capture, followed by a demo confirmation screen.

The shortcode remains:

```text
[atlas_solar_configurator]
```

## Architecture

- `atlas-solar-configurator.php` boots the plugin and defines version/constants.
- `includes/class-plugin.php` wires assets, shortcode and admin.
- `includes/class-assets.php` registers/enqueues frontend CSS and JavaScript.
- `includes/class-shortcode.php` renders the configurator.
- `includes/class-settings.php` exposes the admin status summary.
- `includes/class-session.php` provides an anonymous browser session id.
- `admin/` contains the WordPress admin page.
- `templates/steps/` contains the multi-step markup.
- `public/js/configurator.js` contains the mini-SPA state/navigation engine.
- `public/css/configurator.css` contains responsive, `asc-`-prefixed styles.

## State and persistence

The configurator stores demo state in browser `localStorage`, including address, property type, ownership, consumption band, optional annual kWh, energy profile, mock result, contact fields and campaign attribution.

Legacy 0.1.0 state is migrated safely. In particular, an address entered without real map/property confirmation is never considered confirmed: `address.confirmed` remains `false`.

## Mock result

The Step 4 result is deliberately labelled **DEMO / STIMA DIMOSTRATIVA**. It is deterministic and based on the selected consumption band, with optional adjustment from annual kWh. It is not a technical roof assessment and must not be presented as one.

## Boundaries

Version 0.2.0 does **not** call:

- ATLAS APIs;
- geocoders;
- maps;
- PVGIS;
- roof geometry services;
- email, CRM or appointment systems;
- external analytics providers.

No lead is persisted to WordPress or transmitted externally. Contact information exists only in the browser demo state.

## Tracking foundation

Internal browser events include:

- `configurator_view`
- `address_started`
- `address_submitted`
- `property_completed`
- `consumption_completed`
- `mock_result_viewed`
- `lead_form_started`
- `lead_demo_completed`
- `configurator_reset`

Campaign attribution preserves UTM parameters, `gclid`, `fbclid`, referrer and landing URL.

## Installation

Build a WordPress-compatible ZIP whose single root directory is `atlas-solar-configurator/` and whose archive entries use POSIX forward slashes. The previous PLUGIN-001-R1 packaging finding must be preserved: Windows backslashes in ZIP entry paths are invalid for reliable WordPress installation.

Then:

1. WordPress > Plugins > Add New > Upload Plugin.
2. Upload the 0.2.0 ZIP.
3. Activate the plugin.
4. Add `[atlas_solar_configurator]` to a page.

## Next planned milestone

The next milestone is intentionally not implemented here. A future release may add address resolution and map/property confirmation while preserving the boundary that real technical intelligence lives behind ATLAS-owned services/adapters.
