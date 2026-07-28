[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$required = @(
    'lib/config/plugin.php',
    'lib/config/settings.php',
    'lib/config/requirements.php',
    'lib/config/uninstall.php',
    'lib/shopIndexnowru.plugin.php',
    'lib/classes/shopIndexnowruApiClient.class.php',
    'lib/classes/shopIndexnowruSecret.class.php',
    'locale/ru_RU/LC_MESSAGES/shop_indexnowru.po',
    'locale/ru_RU/LC_MESSAGES/shop_indexnowru.mo',
    'README.md',
    'CHANGELOG.md',
    'LICENSE.txt',
    'build.ps1'
)

foreach ($relative in $required) {
    $path = Join-Path $root $relative
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw "Missing required file: $relative"
    }
}

$phpFiles = Get-ChildItem -LiteralPath (Join-Path $root 'lib') -Recurse -Filter '*.php'
$source = ($phpFiles | ForEach-Object {
    [System.IO.File]::ReadAllText($_.FullName)
}) -join "`n"

$requiredPatterns = @(
    "product_save",
    "https://index-now\.ru/api/v1/submit",
    "Authorization",
    "shopProductModel::STATUS_ACTIVE",
    "getProductUrl\(true,\s*true,\s*true\)",
    "aes-256-gcm",
    "_wp\('Enable automatic submission'\)",
    "'vendor'\s*=>\s*0\s*,"
)
foreach ($pattern in $requiredPatterns) {
    if ($source -notmatch $pattern) {
        throw "Required implementation pattern is missing: $pattern"
    }
}

$forbiddenPatterns = @(
    "var_dump\s*\(",
    "print_r\s*\(",
    "error_log\s*\(",
    "waLog::log\([^;]*(api_key|Authorization|Bearer)",
    "https?://(?!index-now\.ru|developers\.webasyst\.com|github\.com/webasyst|www\.gnu\.org)"
)
foreach ($pattern in $forbiddenPatterns) {
    if ($source -match $pattern) {
        throw "Forbidden implementation pattern found: $pattern"
    }
}

$pluginConfig = [System.IO.File]::ReadAllText((Join-Path $root 'lib/config/plugin.php'))
if ($pluginConfig -notmatch "'version'\s*=>\s*'1\.0\.0'") {
    throw 'Plugin version is not 1.0.0.'
}

$buildScript = [System.IO.File]::ReadAllText((Join-Path $root 'build.ps1'))
if ($buildScript -notmatch 'Marketplace build refused' -or
    $buildScript -notmatch 'VendorId' -or
    $buildScript -notmatch 'tar\.gz') {
    throw 'Marketplace vendor guard or tar.gz packaging guard is missing.'
}

Write-Output "OK: static checks passed for $($phpFiles.Count) PHP files"
