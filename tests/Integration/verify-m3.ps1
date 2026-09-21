param([string]$App = 'E:\progetti_lavoro\ASP\rolewarden-app-test')
$ErrorActionPreference = 'Stop'
$runtime = Join-Path $PSScriptRoot '.m3-app'
if (Test-Path -LiteralPath $runtime) { throw 'Temporary app already exists; inspect before retrying.' }
if (-not $env:RW_DB_PASSWORD) { throw 'Set RW_DB_PASSWORD.' }
$originalEnvHash = (Get-FileHash -LiteralPath (Join-Path $App '.env')).Hash
try {
    New-Item -ItemType Directory -Path $runtime | Out-Null
    foreach ($item in @('app','public','spark')) {
        Copy-Item -LiteralPath (Join-Path $App $item) -Destination $runtime -Recurse
    }
    # Do not copy .env: credentials must never be written into temporary files.
    New-Item -ItemType Junction -Path (Join-Path $runtime 'vendor') -Target (Join-Path $App 'vendor') | Out-Null
    foreach ($directory in @('cache','logs','session','uploads','debugbar')) {
        New-Item -ItemType Directory -Path (Join-Path $runtime "writable/$directory") -Force | Out-Null
    }
    & php (Join-Path $PSScriptRoot 'verify-m3.php') $runtime $App
    $result = $LASTEXITCODE
} finally {
    $expected = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '.m3-app'))
    if ([IO.Path]::GetFullPath($runtime) -ne $expected) { throw 'Unexpected cleanup path' }
    if (Test-Path -LiteralPath (Join-Path $runtime 'RESTORE-FAILED')) { throw 'Database restore failed; runtime retained for recovery.' }
    $vendor = Join-Path $runtime 'vendor'
    if (Test-Path -LiteralPath $vendor) { (Get-Item -LiteralPath $vendor).Delete() }
    if (Test-Path -LiteralPath $runtime) { Remove-Item -LiteralPath $runtime -Recurse -Force }
    if ((Get-FileHash -LiteralPath (Join-Path $App '.env')).Hash -ne $originalEnvHash) { throw 'Sibling .env changed' }
    Write-Output 'PASS safety sibling .env unchanged; temporary app removed'
}
exit $result
