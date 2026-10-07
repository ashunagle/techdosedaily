# Run the responsive sweep or Lighthouse against STAGING from this PC, with the locally installed Chrome.
#   powershell -ExecutionPolicy Bypass -File tests\browser\run.ps1 sweep
#   powershell -ExecutionPolicy Bypass -File tests\browser\run.ps1 lighthouse
# The staging directory login is typed into a hidden prompt, kept only in this process's environment for the
# run, and cleared afterwards. It is never written to disk or printed.
param([ValidateSet('sweep', 'lighthouse')] [string] $What = 'sweep')
$ErrorActionPreference = 'Stop'
$repo = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmmss') + 'Z'
$sec = Read-Host 'Staging directory login as username:password' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
try {
    $env:TDD_BASIC_AUTH = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    $env:BASE = 'https://staging.techdosedaily.com'
    $env:CHROME_PATH = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
    if ($What -eq 'sweep') {
        $out = Join-Path $repo "tests\staging\out\sweep-$stamp"
        Push-Location (Join-Path $repo 'tests\browser')
        node sweep.mjs $out
        Pop-Location
    } else {
        $out = Join-Path $repo "tests\staging\out\lighthouse-$stamp.json"
        Push-Location (Join-Path $repo 'tests\perf')
        node lh.mjs pages.json | Out-File -Encoding utf8 $out
        Pop-Location
        Write-Host "Lighthouse results: $out"
    }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    Remove-Item Env:TDD_BASIC_AUTH -ErrorAction SilentlyContinue
}
