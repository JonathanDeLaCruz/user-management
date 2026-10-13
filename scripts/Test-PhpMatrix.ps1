$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Push-Location $projectRoot
try {
    New-Item -ItemType Directory -Force reports | Out-Null
    foreach ($phpVersion in @('8.2','8.3','8.4','8.5')) {
        $phpExecutable = Join-Path $projectRoot ".runtimes\$phpVersion\php.exe"
        Write-Output "Testing PHP $phpVersion"
        $testOutput = & $phpExecutable vendor\bin\phpunit --display-all-issues 2>&1
        $testExitCode = $LASTEXITCODE
        $testOutput | Tee-Object -FilePath "reports\php-$phpVersion.txt"
        if ($testExitCode -ne 0) { throw "Tests failed on PHP $phpVersion." }
    }
    & (Join-Path $projectRoot '.runtimes\8.4\php.exe') vendor\bin\phpstan analyse --memory-limit=512M --no-progress
    if ($LASTEXITCODE -ne 0) { throw 'PHPStan failed.' }
} finally { Pop-Location }
