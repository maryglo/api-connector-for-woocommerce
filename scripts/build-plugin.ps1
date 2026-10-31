$ErrorActionPreference = 'Stop'

$pluginSlug = 'storedispatcher-outbound-integration-for-woocommerce'
$repoRoot = Split-Path -Parent $PSScriptRoot
$buildRoot = Join-Path $repoRoot 'build'
$packageRoot = Join-Path $buildRoot $pluginSlug
$zipPath = Join-Path $buildRoot "$pluginSlug.zip"

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
	throw 'Composer is required to build the plugin release package.'
}

composer install --no-dev --optimize-autoloader --working-dir="$repoRoot"
if ($LASTEXITCODE -ne 0) {
	throw 'Composer failed to install the runtime autoloader.'
}

if (Test-Path -LiteralPath $packageRoot) {
	Remove-Item -LiteralPath $packageRoot -Recurse -Force
}
if (Test-Path -LiteralPath $zipPath) {
	Remove-Item -LiteralPath $zipPath -Force
}

New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null
$packageItems = @(
	'storedispatcher-outbound-integration-for-woocommerce.php',
	'uninstall.php',
	'readme.txt',
	'composer.json',
	'composer.lock',
	'src',
	'languages',
	'vendor'
)
foreach ($item in $packageItems) {
	Copy-Item -LiteralPath (Join-Path $repoRoot $item) -Destination $packageRoot -Recurse
}

$packageAssets = Join-Path $packageRoot 'assets'
New-Item -ItemType Directory -Path $packageAssets -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $repoRoot 'assets\css') -Destination $packageAssets -Recurse
Copy-Item -LiteralPath (Join-Path $repoRoot 'assets\js') -Destination $packageAssets -Recurse

Compress-Archive -Path $packageRoot -DestinationPath $zipPath -CompressionLevel Optimal
Write-Output "Created $zipPath"
