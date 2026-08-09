$ErrorActionPreference = 'Continue'
Write-Output 'PS_OK'
$targets = @(
  @{ n = 'ALLOW'; host = '192.168.100.54'; url = 'http://192.168.100.54:8080/' },
  @{ n = 'BLOCK'; host = '198.18.0.10'; url = 'http://198.18.0.10:8080/' }
)
foreach ($t in $targets) {
  try {
    $sw = [Diagnostics.Stopwatch]::StartNew()
    $r = Invoke-WebRequest -Uri $t.url -UseBasicParsing -TimeoutSec 5
    $sw.Stop()
    $body = ($r.Content -replace "[\r\n]+", ' ')
    Write-Output ($t.n + ' STATUS=' + [int]$r.StatusCode + ' TIME_MS=' + $sw.ElapsedMilliseconds + ' BODY=' + $body)
  } catch {
    if (-not $sw) { $sw = [Diagnostics.Stopwatch]::StartNew(); $sw.Stop() } else { $sw.Stop() }
    $msg = ($_.Exception.Message -replace "[\r\n]+", ' ')
    Write-Output ($t.n + ' FAIL ERR=' + $msg + ' TIME_MS=' + $sw.ElapsedMilliseconds)
  }
  $tn = Test-NetConnection -ComputerName $t.host -Port 8080 -WarningAction SilentlyContinue
  Write-Output ($t.n + ' TNC_TCP=' + $tn.TcpTestSucceeded)
}
