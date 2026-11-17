param(
    [ValidateSet('serve', 'reverb', 'queue', 'assets', 'build', 'test', 'provision', 'browser', 'format')]
    [string]$Task = 'serve'
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$phpPath = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpPath)) {
    $phpPath = (Get-Command php -ErrorAction Stop).Source
}
$localNode = Get-ChildItem -LiteralPath .tools -Directory -Filter 'node-*-win-x64' -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1
if ($localNode) { $env:PATH = $localNode.FullName + ';' + $env:PATH }
$env:PATH = (Split-Path -Parent $phpPath) + ';' + $env:PATH
$env:PHP_INI_SCAN_DIR = Join-Path (Get-Location) 'scripts\php'
$env:COMPOSER_HOME = Join-Path (Get-Location) '.tools\composer-home'
$env:COMPOSER_CACHE_DIR = Join-Path (Get-Location) '.tools\composer-cache'
$env:npm_config_cache = Join-Path (Get-Location) '.tools\npm-cache'
switch ($Task) {
    'serve' { & $phpPath artisan serve --host=127.0.0.1 --port=8000 --no-reload }
    'reverb' { & $phpPath artisan reverb:start --host=127.0.0.1 --port=8080 }
    'queue' { & $phpPath artisan queue:work --queue=realtime,default --tries=4 --timeout=30 }
    'assets' { & npm.cmd run dev }
    'build' { & npm.cmd run build }
    'test' { & $phpPath artisan test --compact }
    'provision' { & $phpPath artisan app:bootstrap-hr }
    'browser' { & npm.cmd run test:browser }
    'format' { & $phpPath vendor\bin\pint --dirty --format agent; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }; & npm.cmd run format }
}
exit $LASTEXITCODE
