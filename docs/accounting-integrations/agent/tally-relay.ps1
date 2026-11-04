<#
.SYNOPSIS
    Relay between the ERP and a TallyPrime on this network.

.DESCRIPTION
    Tally's HTTP interface sits on the customer's own machine and its docs describe
    no authentication or TLS, so the ERP never calls it. This script only makes
    outbound HTTPS calls: it asks the ERP for jobs, hands each job's XML to Tally,
    and returns Tally's raw reply. All mapping logic lives on the server.

    Never expose Tally's port to the internet to avoid running this.

.EXAMPLE
    .\tally-relay.ps1 -ErpUrl https://erp.example.com -TenantSlug acme -ConnectionId 1 -TokenFile C:\ProgramData\erp-relay\token.txt
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)] [string] $ErpUrl,
    [Parameter(Mandatory)] [string] $TenantSlug,
    [Parameter(Mandatory)] [int] $ConnectionId,
    [Parameter(Mandatory)] [string] $TokenFile,
    [string] $TallyUrl = 'http://localhost:9000',
    [int] $IntervalSeconds = 60,
    [int] $BatchSize = 10,
    [int] $TallyTimeoutSeconds = 300,
    [switch] $Once
)

$ErrorActionPreference = 'Stop'
$AgentVersion = 'ps-1.0.0'

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$erpUri = [Uri] $ErpUrl
if ($erpUri.Scheme -ne 'https' -and -not $erpUri.IsLoopback) {
    throw "ErpUrl must use https, otherwise the token and your accounts travel unencrypted."
}

$token = (Get-Content -LiteralPath $TokenFile -Raw).Trim()
$base = $ErpUrl.TrimEnd('/') + "/api/v1/accounting/integrations/connections/$ConnectionId/tally/agent"
$headers = @{
    Authorization = "Bearer $token"
    'X-Tenant'    = $TenantSlug
    Accept        = 'application/json'
}

function Write-Log([string] $Message) {
    Write-Output ("{0:u} {1}" -f (Get-Date), $Message)
}

function Invoke-Erp([string] $Path, [hashtable] $Body) {
    $json = $Body | ConvertTo-Json -Compress -Depth 4
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)

    return Invoke-RestMethod -Method Post -Uri ($base + $Path) -Headers $headers `
        -ContentType 'application/json; charset=utf-8' -Body $bytes -TimeoutSec 300
}

# Reads the raw bytes, because Windows PowerShell guesses the wrong encoding when
# the server sends no charset, which would mangle names like "Müller".
function Send-ToTally([string] $Xml) {
    $request = [Net.HttpWebRequest]::Create($TallyUrl)
    $request.Method = 'POST'
    $request.ContentType = 'text/xml; charset=utf-8'
    $request.Timeout = $TallyTimeoutSeconds * 1000
    $request.ReadWriteTimeout = $TallyTimeoutSeconds * 1000

    $payload = [Text.Encoding]::UTF8.GetBytes($Xml)
    $request.ContentLength = $payload.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($payload, 0, $payload.Length) } finally { $stream.Close() }

    $response = $request.GetResponse()
    try {
        $buffer = New-Object IO.MemoryStream
        $response.GetResponseStream().CopyTo($buffer)
        $bytes = $buffer.ToArray()
    } finally {
        $response.Close()
    }

    if ($bytes.Length -ge 2 -and $bytes[0] -eq 0xFF -and $bytes[1] -eq 0xFE) {
        return [Text.Encoding]::Unicode.GetString($bytes, 2, $bytes.Length - 2)
    }
    if ($bytes.Length -ge 2 -and $bytes[0] -eq 0xFE -and $bytes[1] -eq 0xFF) {
        return [Text.Encoding]::BigEndianUnicode.GetString($bytes, 2, $bytes.Length - 2)
    }

    return [Text.Encoding]::UTF8.GetString($bytes)
}

function Invoke-Cycle {
    do {
        $poll = Invoke-Erp '/poll' @{ limit = $BatchSize; agent_version = $AgentVersion }
        $jobs = @($poll.data)

        foreach ($job in $jobs) {
            # Only a failure talking to Tally counts as a transport error. If reporting
            # to the ERP fails after Tally accepted the voucher, saying "transport error"
            # would get the voucher sent to Tally a second time.
            $report = $null
            try {
                $report = @{ response_body = (Send-ToTally $job.request_xml) }
            } catch [Net.WebException] {
                $report = @{ transport_error = $_.Exception.Message }
            }
            $result = Invoke-Erp "/jobs/$($job.id)/result" $report
            Write-Log "job $($job.id) $($job.kind): $($result.data.status) $($result.data.detail)"
        }
    } while ($jobs.Count -gt 0)
}

do {
    try {
        Invoke-Cycle
    } catch {
        # Keep running through network blips; a lost result is re-queued by the server.
        Write-Log "ERROR $($_.Exception.Message)"
        if ($Once) { exit 1 }
    }

    if (-not $Once) { Start-Sleep -Seconds $IntervalSeconds }
} while (-not $Once)
