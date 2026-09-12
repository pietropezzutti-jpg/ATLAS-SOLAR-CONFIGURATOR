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

if ($main -notmatch 'Version:\s*0\.3\.0') {
    throw 'Plugin version header is not 0.3.0.'
}

if ($main -notmatch "define\('ASC_VERSION', '0\.3\.0'\)") {
    throw 'ASC_VERSION is not 0.3.0.'
}

if ($main -notmatch "includes/class-geocoder\.php") {
    throw 'Geocoder class is not loaded by the plugin bootstrap.'
}

$plugin = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'includes/class-plugin.php') `
    -Raw

if ($plugin -notmatch "add_shortcode\('atlas_solar_configurator'") {
    throw 'Shortcode registration was not found.'
}

if ($plugin -notmatch "rest_api_init") {
    throw 'REST geocoder registration was not found.'
}

$settings = Get-Content `
    -LiteralPath (Join-Path $PluginRoot 'includes/class-settings.php') `
    -Raw

foreach ($token in @(
    'nominatim.openstreetmap.org/search',
    'tile.openstreetmap.org/{z}/{x}/{y}.png',
    'geocodeUrl',
    'propertyZoom'
)) {
    if (-not $settings.Contains($token)) {
        throw "Missing settings/map token: $token"
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

if ($geocoder -match '(?i)autocomplete') {
    $commentsRemoved = $geocoder -replace '(?ms)/\*.*?\*/', ''
    $commentsRemoved = $commentsRemoved -replace '(?m)^\s*\*.*$', ''

    if ($commentsRemoved -match '(?i)autocomplete') {
        throw 'Autocomplete implementation is forbidden in PLUGIN-003.'
    }
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

if ($readme -notmatch 'Stable tag:\s*0\.3\.0') {
    throw 'readme.txt stable tag is not 0.3.0.'
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
