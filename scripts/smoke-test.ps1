$ErrorActionPreference = 'Stop'

$baseUrl = "http://localhost:$env:APP_PORT"
$deadline = (Get-Date).AddSeconds(90)
$health = $null

Write-Host "Checking KosanHub application health..."
Write-Host "Target URL: $baseUrl/health"

while ((Get-Date) -lt $deadline) {
    try {
        $health = Invoke-RestMethod `
            -Uri "$baseUrl/health" `
            -TimeoutSec 10

        break
    }
    catch {
        Write-Host "Application is not ready yet. Retrying..."
        Start-Sleep -Seconds 3
    }
}

if ($null -eq $health) {
    throw "Smoke Test FAILED: Application health endpoint is unavailable."
}

if ($health.status -ne 'ok') {
    throw "Smoke Test FAILED: Application status is not OK."
}

if ($health.database -ne 'connected') {
    throw "Smoke Test FAILED: Database is not connected."
}

Write-Host "Application status: $($health.status)"
Write-Host "Database status: $($health.database)"
Write-Host "KosanHub Smoke Test PASSED."
