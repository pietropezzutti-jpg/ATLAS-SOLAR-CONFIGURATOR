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
    'admin/class-admin.php',
    'admin/views/settings-page.php',
    'public/css/configurator.css',
    'public/js/configurator.js',
    'templates/configurator.php',
    'templates/steps/step-address.php',
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

$main = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'atlas-solar-configurator.php') `
    -Raw

if ($main -notmatch 'Plugin Name:\s*ATLAS Solar Lead Configurator') {
    throw 'Main plugin header is missing.'
}

if ($main -notmatch 'Version:\s*0\.4\.0') {
    throw 'Plugin version header is not 0.4.0.'
}

if ($main -notmatch "define\('ASC_VERSION', '0\.4\.0'\)") {
    throw 'ASC_VERSION is not 0.4.0.'
}

foreach ($bootstrapToken in @(
    'includes/class-geocoder.php',
    'includes/class-atlas-boundary.php'
)) {
    if (-not $main.Contains($bootstrapToken)) {
        throw "Missing bootstrap dependency: $bootstrapToken"
    }
}

$plugin = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'includes/class-plugin.php') `
    -Raw

if ($plugin -notmatch "add_shortcode\('atlas_solar_configurator'") {
    throw 'Shortcode registration was not found.'
}

foreach ($token in @(
    'Atlas_Solar_Configurator_Geocoder',
    'Atlas_Solar_Configurator_Atlas_Boundary',
    'rest_api_init'
)) {
    if (-not $plugin.Contains($token)) {
        throw "Missing plugin composition token: $token"
    }
}

$settings = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'includes/class-settings.php') `
    -Raw

foreach ($token in @(
    'nominatim.openstreetmap.org/search',
    'tile.openstreetmap.org/{z}/{x}/{y}.png',
    'geocodeUrl',
    'propertyZoom',
    'Contract v1 ready; transport disabled',
    'ATLAS-owned; not called by WordPress'
)) {
    if (-not $settings.Contains($token)) {
        throw "Missing settings token: $token"
    }
}

$geocoder = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'includes/class-geocoder.php') `
    -Raw

foreach ($token in @(
    'atlas-solar-configurator/v1',
    '/geocode',
    'wp_remote_get',
    'set_transient',
    'countrycodes',
    'user-agent',
    'nominatim-compatible'
)) {
    if (-not $geocoder.Contains($token)) {
        throw "Missing geocoder token: $token"
    }
}

$boundary = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'includes/class-atlas-boundary.php') `
    -Raw

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
    throw 'PLUGIN-004 boundary must not perform ATLAS transport.'
}

$template = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'templates/steps/step-address.php') `
    -Raw

foreach ($token in @(
    'data-asc-map',
    'data-asc-candidate-list',
    'data-asc-confirm-position',
    'CONFERMA POSIZIONE E CONTINUA'
)) {
    if (-not $template.Contains($token)) {
        throw "Missing address/map template token: $token"
    }
}

$css = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'public/css/configurator.css') `
    -Raw

$forbiddenCss = @(
    '.button',
    '.container',
    '.row',
    '.form'
)

foreach ($selector in $forbiddenCss) {
    if ($css.Contains($selector)) {
        throw "Forbidden generic CSS selector found: $selector"
    }
}

foreach ($selector in @(
    '.asc-map',
    '.asc-map-marker-pin',
    '.asc-candidate-button'
)) {
    if (-not $css.Contains($selector)) {
        throw "Missing PLUGIN-003 CSS selector: $selector"
    }
}

$js = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'public/js/configurator.js') `
    -Raw

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
        throw "Missing PLUGIN-003 JavaScript token: $token"
    }
}

$readme = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'readme.txt') `
    -Raw

if ($readme -notmatch 'Stable tag:\s*0\.4\.0') {
    throw 'readme.txt stable tag is not 0.4.0.'
}

foreach ($token in @(
    'assessment-contract',
    'transmitted=false',
    'atlasTransport=disabled'
)) {
    if (-not $readme.Contains($token)) {
        throw "Missing PLUGIN-004 readme token: $token"
    }
}

$secretPatterns = @(
    'sk-[A-Za-z0-9]',
    'AIza[0-9A-Za-z_\-]',
    '-----BEGIN PRIVATE KEY-----',
    'password\s*=',
    'api[_-]?key\s*='
)

$files = Get-ChildItem `
    -LiteralPath $PluginRoot `
    -Recurse `
    -File |
    Where-Object {
        $_.FullName -notlike '*\.git\*' `
            -and $_.FullName -notlike '*\build\*' `
            -and $_.FullName -ne $PSCommandPath
    }

foreach ($file in $files) {
    $content = Get-Content `
        -LiteralPath $file.FullName `
        -Raw

    foreach ($pattern in $secretPatterns) {
        if ($content -match $pattern) {
            throw "Potential secret pattern found in $($file.FullName)"
        }
    }
}

Write-Output 'VALIDATION=PASS'
