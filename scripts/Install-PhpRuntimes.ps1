param([string[]]$Versions = @('8.2','8.3','8.4','8.5'))
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$releaseManifest = Invoke-RestMethod 'https://windows.php.net/downloads/releases/releases.json'
foreach ($phpVersion in $Versions) {
    if ($phpVersion -notin @('8.2','8.3','8.4','8.5')) { throw 'Version must be 8.2, 8.3, 8.4 or 8.5.' }
    $entry = $releaseManifest.$phpVersion
    $build = $entry.PSObject.Properties | Where-Object { $_.Name -match '^nts-.*-x64$' } | Select-Object -First 1
    if (!$build) { throw "No official x64 build available for $phpVersion." }
    $runtimeDirectory = Join-Path $projectRoot ".runtimes\$phpVersion"
    $archiveDirectory = Join-Path $projectRoot '.downloads'
    New-Item -ItemType Directory -Force -Path $runtimeDirectory,$archiveDirectory | Out-Null
    $archive = Join-Path $archiveDirectory $build.Value.zip.path
    Invoke-WebRequest ("https://windows.php.net/downloads/releases/" + $build.Value.zip.path) -OutFile $archive
    if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash -ne $build.Value.zip.sha256) { throw 'Official PHP SHA256 verification failed.' }
    Expand-Archive -LiteralPath $archive -DestinationPath $runtimeDirectory -Force
    $ini = @"
extension_dir="$runtimeDirectory\ext"
extension=curl
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=pdo_mysql
extension=gd
extension=zip
date.timezone=America/Mexico_City
error_reporting=E_ALL
display_errors=Off
log_errors=On
"@
    Set-Content -LiteralPath (Join-Path $runtimeDirectory 'php.ini') -Value $ini
    & (Join-Path $runtimeDirectory 'php.exe') --version
}
