[CmdletBinding()]
param(
    [switch]$Marketplace,
    [ValidateRange(0, 2147483647)]
    [int]$VendorId = 0,
    [string]$OutputDirectory = ''
)

$ErrorActionPreference = 'Stop'
$pluginId = 'indexnowru'
$version = '1.0.0'
$sourceRoot = $PSScriptRoot

if ($Marketplace -and $VendorId -le 0) {
    throw 'Marketplace build refused: pass the positive numeric Webasyst developer ID with -VendorId.'
}
if (-not $Marketplace -and $VendorId -gt 0) {
    throw 'VendorId is accepted only together with -Marketplace.'
}

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $sourceRoot '..\..\outputs\webasyst-1.0.0'
}
$OutputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)
[System.IO.Directory]::CreateDirectory($OutputDirectory) | Out-Null

$temporaryRoot = [System.IO.Path]::GetFullPath(
    (Join-Path ([System.IO.Path]::GetTempPath()) ('indexnowru-build-' + [guid]::NewGuid().ToString('N')))
)
$systemTemp = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
if (-not $temporaryRoot.StartsWith($systemTemp, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Unsafe temporary path: $temporaryRoot"
}

$stagePlugin = Join-Path $temporaryRoot $pluginId
$archiveName = if ($Marketplace) {
    "$pluginId-$version.tar.gz"
} else {
    "$pluginId-$version-local.tar.gz"
}
$archivePath = Join-Path $OutputDirectory $archiveName

try {
    [System.IO.Directory]::CreateDirectory($stagePlugin) | Out-Null

    foreach ($directory in @('lib', 'locale')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $directory) -Destination $stagePlugin -Recurse
    }
    foreach ($file in @('README.md', 'CHANGELOG.md', 'LICENSE.txt')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination $stagePlugin
    }

    $pluginConfigPath = Join-Path $stagePlugin 'lib\config\plugin.php'
    $pluginConfig = [System.IO.File]::ReadAllText($pluginConfigPath)
    if ($Marketplace) {
        $replacement = "'vendor'      => $VendorId,"
        $pluginConfig = [regex]::Replace(
            $pluginConfig,
            "'vendor'\s*=>\s*0\s*,",
            $replacement,
            [System.Text.RegularExpressions.RegexOptions]::CultureInvariant
        )
        if ($pluginConfig -notmatch "'vendor'\s*=>\s*$VendorId\s*,") {
            throw 'Marketplace build refused: vendor placeholder was not replaced.'
        }
        [System.IO.File]::WriteAllText(
            $pluginConfigPath,
            $pluginConfig,
            [System.Text.UTF8Encoding]::new($false)
        )
    } elseif ($pluginConfig -notmatch "'vendor'\s*=>\s*0\s*,") {
        throw 'Local build refused: source vendor placeholder must be exactly 0.'
    }

    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }
    & tar -czf $archivePath -C $temporaryRoot $pluginId
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $archivePath)) {
        throw 'tar failed to create the Webasyst package.'
    }

    $entries = @(& tar -tf $archivePath)
    if ($LASTEXITCODE -ne 0 -or $entries.Count -eq 0) {
        throw 'The generated archive could not be read.'
    }
    $invalidEntry = $entries | Where-Object {
        $_ -ne "$pluginId/" -and -not $_.StartsWith("$pluginId/")
    } | Select-Object -First 1
    if ($invalidEntry) {
        throw "Archive contains an invalid root entry: $invalidEntry"
    }
    if ($entries -contains "$pluginId/build.ps1" -or $entries -match "^$pluginId/tests/") {
        throw 'Development-only files leaked into the installable archive.'
    }

    $hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()
    [pscustomobject]@{
        Archive = $archivePath
        SHA256 = $hash
        Marketplace = [bool]$Marketplace
        VendorId = $VendorId
    }
} finally {
    if (Test-Path -LiteralPath $temporaryRoot) {
        $resolvedTemporaryRoot = [System.IO.Path]::GetFullPath($temporaryRoot)
        if ($resolvedTemporaryRoot.StartsWith($systemTemp, [System.StringComparison]::OrdinalIgnoreCase) -and
            [System.IO.Path]::GetFileName($resolvedTemporaryRoot).StartsWith('indexnowru-build-')) {
            Remove-Item -LiteralPath $resolvedTemporaryRoot -Recurse -Force
        }
    }
}

