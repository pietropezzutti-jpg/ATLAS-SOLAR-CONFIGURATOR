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
    'admin/class-admin.php',
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

$main = Get-Content -LiteralPath (Join-Path $PluginRoot 'atlas-solar-configurator.php') -Raw
if ($main -notmatch 'Plugin Name:\s*ATLAS Solar Lead Configurator') {
    throw 'Main plugin header is missing.'
}

if ($main -notmatch 'Version:\s*0\.1\.0') {
    throw 'Plugin version header is not 0.1.0.'
}

$plugin = Get-Content -LiteralPath (Join-Path $PluginRoot 'includes/class-plugin.php') -Raw
if ($plugin -notmatch "add_shortcode\('atlas_solar_configurator'") {
    throw 'Shortcode registration was not found.'
}

$css = Get-Content -LiteralPath (Join-Path $PluginRoot 'public/css/configurator.css') -Raw
$forbiddenCss = @('.button', '.container', '.row', '.form')
foreach ($selector in $forbiddenCss) {
    if ($css.Contains($selector)) {
        throw "Forbidden generic CSS selector found: $selector"
    }
}

$js = Get-Content -LiteralPath (Join-Path $PluginRoot 'public/js/configurator.js') -Raw
foreach ($eventName in @('configurator_view', 'address_started', 'address_submitted')) {
    if (-not $js.Contains($eventName)) {
        throw "Missing event name: $eventName"
    }
}

$secretPatterns = @(
    'sk-[A-Za-z0-9]',
    'AIza[0-9A-Za-z_\-]',
    '-----BEGIN PRIVATE KEY-----',
    'password\s*=',
    'api[_-]?key\s*='
)

$files = Get-ChildItem -LiteralPath $PluginRoot -Recurse -File | Where-Object {
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
