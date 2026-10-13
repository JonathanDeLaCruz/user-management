param([ValidateSet('8.2','8.3','8.4','8.5')][string]$PhpVersion = '8.4', [int]$Port = 8080)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$phpExecutable = Join-Path $projectRoot ".runtimes\$PhpVersion\php.exe"
if (!(Test-Path -LiteralPath $phpExecutable)) { throw "Run scripts/Install-PhpRuntimes.ps1 first to install PHP $PhpVersion." }
Write-Output "PHP $PhpVersion — http://127.0.0.1:$Port"
& $phpExecutable -S "127.0.0.1:$Port" -t (Join-Path $projectRoot 'demo\web') (Join-Path $projectRoot 'demo\web\router.php')
