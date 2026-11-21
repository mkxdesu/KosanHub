$ErrorActionPreference = 'Stop'

$port = if ([string]::IsNullOrWhiteSpace($env:APP_PORT)) {
    '8082'
} else {
    $env:APP_PORT
}

$baseUrl = "http://localhost:$port"
$healthTimeout = 90
$workerTimeout = 90

Write-Host ""
Write-Host "============================================"
Write-Host "       KOSANHUB SMOKE TEST"
Write-Host "============================================"
Write-Host "Application URL : $baseUrl"
Write-Host "Started at      : $(Get-Date)"
Write-Host "============================================"
Write-Host ""

function Test-ApplicationHealth {

    Write-Host "[1/6] Checking application and database health..."

    $deadline = (Get-Date).AddSeconds($healthTimeout)
    $healthy = $false

    while ((Get-Date) -lt $deadline) {

        try {

            $response = Invoke-WebRequest `
                -Uri "$baseUrl/health" `
                -UseBasicParsing `
                -TimeoutSec 10

            if ($response.StatusCode -ne 200) {
                throw "Unexpected HTTP status: $($response.StatusCode)"
            }

            $health = $response.Content | ConvertFrom-Json

            if (
                $health.status -eq 'ok' -and
                $health.database -eq 'connected'
            ) {

                $healthy = $true

                Write-Host "[SUCCESS] Application is healthy."
                Write-Host "[SUCCESS] HTTP Status: 200"
                Write-Host "[SUCCESS] Database: connected"

                return
            }

            Write-Host "[WAIT] Application or database is not ready."

        }
        catch {

            Write-Host "[WAIT] Health check: $($_.Exception.Message)"

        }

        Start-Sleep -Seconds 3
    }

    if (-not $healthy) {
        throw "Application health check failed after $healthTimeout seconds."
    }
}

function Test-WorkerRunning {

    Write-Host ""
    Write-Host "[2/6] Checking Redis worker container..."

    $workerStatus = & docker compose ps --status running worker 2>&1
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        throw "Unable to inspect worker container: $workerStatus"
    }

    if ([string]::IsNullOrWhiteSpace(($workerStatus | Out-String).Trim())) {
        throw "Redis worker container is not running."
    }

    Write-Host "[SUCCESS] Redis worker container is running."
}

function Get-CompletedJobCount {

    $output = & docker compose exec -T worker sh -lc "if [ -f storage/logs/laravel.log ]; then grep -cF 'GenerateMonthlyInvoicesJob finished' storage/logs/laravel.log || true; else echo 0; fi" 2>&1

    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        throw "Unable to read worker log: $output"
    }

    $content = ($output | Out-String).Trim()

    $countLine = $content -split "`r?`n" |
        Where-Object { $_ -match '^\s*\d+\s*$' } |
        Select-Object -Last 1

    if ($null -eq $countLine) {
        throw "Unable to determine completed job count. Output: $content"
    }

    return [int]$countLine.Trim()
}


function Test-ActiveContracts {

    Write-Host ""
    Write-Host "[3/6] Checking active rental contracts..."

    $contracts = @(
        Invoke-RestMethod `
            -Uri "$baseUrl/api/contracts?status=active" `
            -TimeoutSec 15
    )

    if ($contracts.Count -lt 1) {
        throw "No active contracts found. Demo seeder must run before Smoke Test."
    }

    Write-Host "[SUCCESS] Active contracts found: $($contracts.Count)"

}


function Invoke-MonthlyInvoiceGeneration {

    Write-Host ""
    Write-Host "[4/6] Triggering monthly invoice generation..."

    $response = Invoke-WebRequest `
        -Method Post `
        -Uri "$baseUrl/api/invoices/generate-monthly" `
        -ContentType "application/json" `
        -Body '{}' `
        -UseBasicParsing `
        -TimeoutSec 15

    if ($response.StatusCode -ne 202) {
        throw "Expected HTTP 202, received HTTP $($response.StatusCode)."
    }

    $result = $response.Content | ConvertFrom-Json

    $script:period = [string]$result.period

    if ($script:period -notmatch '^\d{4}-(0[1-9]|1[0-2])$') {
        throw "Invalid invoice period returned by API."
    }

    Write-Host "[SUCCESS] Invoice generation request accepted."
    Write-Host "[SUCCESS] HTTP Status: 202"
    Write-Host "[SUCCESS] Invoice period: $script:period"
}


function Wait-ForWorkerProcessing {

    Write-Host ""
    Write-Host "[5/6] Waiting for Redis worker to process the job..."

    $before = Get-CompletedJobCount

    Write-Host "Completed jobs before request: $before"

    Invoke-MonthlyInvoiceGeneration

    $deadline = (Get-Date).AddSeconds($workerTimeout)
    $processed = $false

    while ((Get-Date) -lt $deadline) {

        Start-Sleep -Seconds 3

        $after = Get-CompletedJobCount

        Write-Host "Completed jobs: $after"

        if ($after -gt $before) {

            $processed = $true

            Write-Host "[SUCCESS] Redis worker processed the queued job."

            break
        }
    }

    if (-not $processed) {
        throw "Redis worker did not complete the job within $workerTimeout seconds."
    }

}

function Test-GeneratedInvoices {

    Write-Host ""
    Write-Host "[6/6] Verifying generated invoices..."

    $invoices = @(
        Invoke-RestMethod `
            -Uri "$baseUrl/api/invoices?period=$script:period" `
            -TimeoutSec 15
    )

    if ($invoices.Count -lt 1) {
        throw "No invoices found for period $script:period."
    }

    Write-Host "[SUCCESS] Invoice records found: $($invoices.Count)"
    Write-Host "[SUCCESS] Period: $script:period"

}

try {

    Test-ApplicationHealth

    Test-WorkerRunning

    Test-ActiveContracts

    Wait-ForWorkerProcessing

    Test-GeneratedInvoices

    Write-Host ""
    Write-Host "============================================"
    Write-Host "       KOSANHUB SMOKE TEST PASSED"
    Write-Host "============================================"
    Write-Host "Application : HEALTHY"
    Write-Host "Database    : CONNECTED"
    Write-Host "Worker      : PROCESSED JOB"
    Write-Host "Invoice     : VERIFIED"
    Write-Host "Period      : $script:period"
    Write-Host "============================================"

    exit 0

}
catch {

    Write-Host ""
    Write-Host "============================================"
    Write-Host "       KOSANHUB SMOKE TEST FAILED"
    Write-Host "============================================"
    Write-Host "Error: $($_.Exception.Message)"
    Write-Host "============================================"

    exit 1

}
