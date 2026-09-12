param(
    [string] $PluginRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'

$required = @(
    'atlas-solar-configurator.php',
    'README.md',
    'readme.txt',
    '.gitignore',
    'includes/class-plugin.php',
    'includes/class-assets.php',
    'includes/class-shortcode.php',
    'includes/class-settings.php',
    'includes/class-session.php',
    'includes/class-geocoder.php',
    'includes/class-atlas-boundary.php',
    'includes/class-atlas-transport.php',
    'admin/class-admin.php',
    'admin/views/settings-page.php',
    'public/css/configurator.css',
    'public/js/configurator.js',
    'public/js/national-orthophoto.js',
    'templates/configurator.php',
    'templates/steps/step-address.php',
    'tests/atlas-transport-acceptance.php',
    'tests/geocoder-anncsu-acceptance.php',
    'tests/validate-plugin.ps1'
)

$missing = @()
foreach ($relative in $required) {
    $path = Join-Path $PluginRoot $relative
    if (-not (Test-Path -LiteralPath $path)) {
        $missing += $relative
    }
}
if ($missing.Count -gt 0) {
    throw "Missing required files: $($missing -join ', ')"
}

$main = Get-Content -LiteralPath (Join-Path $PluginRoot 'atlas-solar-configurator.php') -Raw
if ($main -notmatch 'Plugin Name:\s*ATLAS Solar Lead Configurator') {
    throw 'Main plugin header is missing.'
}
if ($main -notmatch 'Version:\s*0\.5\.0') {
    throw 'Plugin version header is not 0.5.0.'
}
if ($main -notmatch "define\('ASC_VERSION', '0\.5\.0'\)") {
    throw 'ASC_VERSION is not 0.5.0.'
}

foreach ($bootstrapToken in @(
    'includes/class-geocoder.php',
    'includes/class-atlas-boundary.php',
    'includes/class-atlas-transport.php'
)) {
    if (-not $main.Contains($bootstrapToken)) {
        throw "Missing bootstrap dependency: $bootstrapToken"
    }
}

$plugin = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-plugin.php') -Raw
if ($plugin -notmatch "add_shortcode\('atlas_solar_configurator'") {
    throw 'Shortcode registration was not found.'
}
foreach ($token in @(
    'Atlas_Solar_Configurator_Geocoder',
    'Atlas_Solar_Configurator_Atlas_Boundary',
    'Atlas_Solar_Configurator_Atlas_Transport',
    'rest_api_init'
)) {
    if (-not $plugin.Contains($token)) {
        throw "Missing plugin composition token: $token"
    }
}

$settings = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-settings.php') -Raw
foreach ($token in @(
    'nominatim.openstreetmap.org/search',
    'tile.openstreetmap.org/{z}/{x}/{y}.png',
    'geocodeUrl',
    'propertyZoom',
    'transport adapter foundation available',
    'public boundary disconnected',
    'never exposed to frontend',
    'wms.pcn.minambiente.it/ogc?map=/ms_ogc/WMS_v1.3/raster/ortofoto_colore_12.map',
    'OI.ORTOIMMAGINI.2012',
    'aerial_wms_url',
    'aerial_wms_layers',
    'autoEnableAtPropertyZoom'
)) {
    if (-not $settings.Contains($token)) {
        throw "Missing settings token: $token"
    }
}

$assets = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-assets.php') -Raw
foreach ($token in @(
    'asc-national-orthophoto',
    'public/js/national-orthophoto.js',
    'ASC_IMAGERY_CONFIG'
)) {
    if (-not $assets.Contains($token)) {
        throw "Missing national orthophoto asset token: $token"
    }
}

$geocoder = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-geocoder.php') -Raw
foreach ($token in @(
    'atlas-solar-configurator/v1',
    '/geocode',
    'wp_remote_get',
    'set_transient',
    'countrycodes',
    'user-agent',
    'nominatim-compatible',
    "CACHE_STRATEGY_VERSION = '5'",
    'build_structured_address',
    'lookup_structured_candidates',
    "'street' => `$street",
    "'city' => `$city",
    "'layer' => 'address'",
    'structured_exact',
    'ASC_ANNCSU_ADDRESS_API_URL',
    'developers.coseerobe.it/api/v1/anncsu-indirizzi-slim',
    'anncsu-community-open-data',
    'lookup_anncsu_exact_candidates',
    'anncsu_record_is_exact',
    "'NOME_COMUNE' => 'ilike.*'",
    "'CIVICO' => 'eq.'",
    'anncsu_exact',
    'out_of_bounds'
)) {
    if (-not $geocoder.Contains($token)) {
        throw "Missing geocoder token: $token"
    }
}
foreach ($forbiddenGeocoderToken in @(
    'build_locality_query',
    "fallback_mode = 'locality'"
)) {
    if ($geocoder.Contains($forbiddenGeocoderToken)) {
        throw "Municipality-centre fallback must not be present: $forbiddenGeocoderToken"
    }
}

$boundary = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-atlas-boundary.php') -Raw
foreach ($token in @(
    "CONTRACT_VERSION = '1.0'",
    '/assessment-contract',
    'BOUNDARY_READY',
    "'transmitted' => false",
    "'atlasTransport' => 'disabled'",
    'PREVIEW_AVAILABLE',
    'MANUAL_FALLBACK',
    'DISAMBIGUATION_REQUIRED',
    'IDENTITY_NOT_RESOLVED',
    'IDENTITY_AMBIGUOUS',
    'RNDT_RECORD_NOT_FOUND',
    'RNDT_RECORD_AMBIGUOUS',
    'propertyPosition',
    'position_not_confirmed',
    'forbidden_fields'
)) {
    if (-not $boundary.Contains($token)) {
        throw "Missing PLUGIN-004 boundary token: $token"
    }
}
if ($boundary -match 'wp_remote_(get|post|request)') {
    throw 'Public assessment boundary must remain transport-disconnected in PLUGIN-005 R1.'
}

$transport = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-atlas-transport.php') -Raw
foreach ($token in @(
    '/property-intelligence/roof/click-preview',
    'ASC_ATLAS_BASE_URL',
    'ASC_ATLAS_BEARER_TOKEN',
    'build_preview_request',
    'request_preview',
    "'latitude' => `$latitude",
    "'longitude' => `$longitude",
    'wp_remote_post',
    "'Authorization' => 'Bearer ' . `$token",
    'asc_atlas_transport_not_configured',
    'public_result_statuses',
    'publicBoundaryConnected'
)) {
    if (-not $transport.Contains($token)) {
        throw "Missing PLUGIN-005 transport token: $token"
    }
}
if ($transport.Contains('register_rest_route')) {
    throw 'PLUGIN-005 R1 transport must not expose its own public REST route.'
}
if ($transport -match "\['address'\]|\['sessionId'\]|\['consumption'\]|\['energyProfile'\]|\['contact'\]|\['marketing'\]") {
    throw 'PLUGIN-005 ATLAS request mapping must not forward non-coordinate contract fields.'
}

$transportAcceptance = Get-Content -LiteralPath (Join-Path $PluginRoot 'tests/atlas-transport-acceptance.php') -Raw
foreach ($token in @(
    'pre_http_request',
    'LATITUDE_LONGITUDE_ONLY',
    'SERVER_SIDE_BEARER',
    'WIRE_BODY_COORDINATES_ONLY',
    'ATLAS_HTTP_EXECUTED=False',
    'FINAL=PASS_PLUGIN_005_R1_SERVER_SIDE_ADAPTER_ACCEPTANCE'
)) {
    if (-not $transportAcceptance.Contains($token)) {
        throw "Missing transport acceptance token: $token"
    }
}

$geocoderAcceptance = Get-Content -LiteralPath (Join-Path $PluginRoot 'tests/geocoder-anncsu-acceptance.php') -Raw
foreach ($token in @(
    'pre_http_request',
    'anncsu.example.test',
    'ANNCSU_EXACT_RESOLVED',
    'ANNCSU_PROVIDER',
    'ANNCSU_EXACT_MODE',
    'ANNCSU_OOB_REJECTED',
    'LOCALITY_CENTRE_SUBSTITUTION=False',
    'EXTERNAL_HTTP_EXECUTED=False',
    'FINAL=PASS_PLUGIN_005_EXACT_ANNCSU_SECONDARY_PROVIDER_ACCEPTANCE'
)) {
    if (-not $geocoderAcceptance.Contains($token)) {
        throw "Missing ANNCSU acceptance token: $token"
    }
}

$template = Get-Content -LiteralPath (Join-Path $PluginRoot 'templates/steps/step-address.php') -Raw
foreach ($token in @(
    'data-asc-map',
    'data-asc-candidate-list',
    'data-asc-confirm-position',
    'CONFERMA POSIZIONE E CONTINUA',
    'ortofoto nazionale',
    'MASE / Geoportale Nazionale',
    '2009-2012'
)) {
    if (-not $template.Contains($token)) {
        throw "Missing address/map template token: $token"
    }
}

$css = Get-Content -LiteralPath (Join-Path $PluginRoot 'public/css/configurator.css') -Raw
$forbiddenCss = @('.button', '.container', '.row', '.form')
foreach ($selector in $forbiddenCss) {
    if ($css.Contains($selector)) {
        throw "Forbidden generic CSS selector found: $selector"
    }
}
foreach ($selector in @('.asc-map', '.asc-map-marker-pin', '.asc-candidate-button')) {
    if (-not $css.Contains($selector)) {
        throw "Missing CSS selector: $selector"
    }
}

$js = Get-Content -LiteralPath (Join-Path $PluginRoot 'public/js/configurator.js') -Raw
foreach ($eventName in @(
    'configurator_view',
    'address_started',
    'address_submitted',
    'address_geocode_started',
    'address_geocode_resolved',
    'address_geocode_ambiguous',
    'address_geocode_not_found',
    'property_position_adjusted',
    'property_position_confirmed',
    'property_completed',
    'consumption_completed',
    'mock_result_viewed',
    'lead_form_started',
    'lead_demo_completed',
    'configurator_reset'
)) {
    if (-not $js.Contains($eventName)) {
        throw "Missing event name: $eventName"
    }
}
foreach ($token in @(
    'window.fetch',
    'mapConfig.geocodeUrl',
    'address.confirmed',
    'location.propertyPosition',
    'L.tileLayer',
    'draggable: true'
)) {
    if (-not $js.Contains($token)) {
        throw "Missing JavaScript token: $token"
    }
}
foreach ($forbiddenFrontendToken in @(
    'ASC_ATLAS_BEARER_TOKEN',
    'Authorization: Bearer',
    'property-intelligence/roof/click-preview',
    'ASC_ANNCSU_ADDRESS_API_URL'
)) {
    if ($js.Contains($forbiddenFrontendToken)) {
        throw "Server-side configuration leaked into frontend JavaScript: $forbiddenFrontendToken"
    }
}

$orthophoto = Get-Content -LiteralPath (Join-Path $PluginRoot 'public/js/national-orthophoto.js') -Raw
foreach ($token in @(
    'ASC_IMAGERY_CONFIG',
    'L.tileLayer.wms',
    'OI.ORTOIMMAGINI.2012',
    'L.CRS.EPSG4326',
    'L.control.layers',
    'autoEnableAtPropertyZoom'
)) {
    if (-not $orthophoto.Contains($token) -and $token -ne 'OI.ORTOIMMAGINI.2012') {
        throw "Missing national orthophoto JavaScript token: $token"
    }
}
if ($orthophoto.Contains('OI.ORTOIMMAGINI.2012')) {
    throw 'National orthophoto layer identifier must come from server-side configuration, not be hard-coded in JavaScript.'
}

$readme = Get-Content -LiteralPath (Join-Path $PluginRoot 'readme.txt') -Raw
if ($readme -notmatch 'Stable tag:\s*0\.5\.0') {
    throw 'readme.txt stable tag is not 0.5.0.'
}
foreach ($token in @(
    'assessment-contract',
    'transmitted=false',
    'atlasTransport=disabled',
    'ASC_ATLAS_BASE_URL',
    'ASC_ATLAS_BEARER_TOKEN',
    '/property-intelligence/roof/click-preview',
    'ASC_ANNCSU_ADDRESS_API_URL',
    'ANNCSU',
    'community'
)) {
    if (-not $readme.Contains($token)) {
        throw "Missing PLUGIN-005 readme token: $token"
    }
}

$secretPatterns = @(
    'sk-[A-Za-z0-9]',
    'AIza[0-9A-Za-z_\-]',
    '-----BEGIN PRIVATE KEY-----',
    'password\s*=',
    'api[_-]?key\s*='
)
$files = Get-ChildItem -LiteralPath $PluginRoot -Recurse -File |
    Where-Object {
        $_.FullName -notlike '*\.git\*' `
            -and $_.FullName -notlike '*\build\*' `
            -and $_.FullName -ne $PSCommandPath
    }
foreach ($file in $files) {
    $content = Get-Content -LiteralPath $file.FullName -Raw
    foreach ($pattern in $secretPatterns) {
        if ($content -match $pattern) {
            throw "Potential secret pattern found in $($file.FullName)"
        }
    }
}

Write-Output 'VALIDATION=PASS'
