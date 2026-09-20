param([string]$App = 'E:\progetti_lavoro\ASP\rolewarden-app-test')
$ErrorActionPreference = 'Stop'
$runtime = Join-Path $PSScriptRoot '.m1-app'
if (Test-Path -LiteralPath $runtime) { throw "Temporary app already exists: $runtime" }
if (-not $env:RW_DB_PASSWORD) { throw 'Set RW_DB_PASSWORD before running.' }
try {
    New-Item -ItemType Directory -Path $runtime | Out-Null
    Copy-Item -LiteralPath (Join-Path $App 'app') -Destination $runtime -Recurse
    Copy-Item -LiteralPath (Join-Path $App 'spark') -Destination $runtime
    Copy-Item -LiteralPath (Join-Path $App '.env') -Destination $runtime
    # Spark changes into the app's web root even for CLI database commands.
    Copy-Item -LiteralPath (Join-Path $App 'public') -Destination $runtime -Recurse
    New-Item -ItemType Junction -Path (Join-Path $runtime 'vendor') -Target (Join-Path $App 'vendor') | Out-Null
    foreach ($directory in @('cache', 'logs', 'session', 'uploads', 'debugbar')) {
        New-Item -ItemType Directory -Path (Join-Path $runtime "writable/$directory") -Force | Out-Null
    }
    & php (Join-Path $PSScriptRoot 'verify-m1.php') $runtime
    $result = $LASTEXITCODE
} finally {
    # Remove the junction itself before recursively removing this verified local directory.
    $expected = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '.m1-app'))
    if ([IO.Path]::GetFullPath($runtime) -ne $expected) { throw 'Unexpected cleanup path' }
    $vendor = Join-Path $runtime 'vendor'
    if (Test-Path -LiteralPath $vendor) { (Get-Item -LiteralPath $vendor).Delete() }
    if (Test-Path -LiteralPath $runtime) { Remove-Item -LiteralPath $runtime -Recurse -Force }
}
exit $result
