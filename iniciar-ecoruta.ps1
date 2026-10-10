<#
  EcoRuta · Inicializador del sistema (uso en la PC de oficina)
  ----------------------------------------------------------------
  Levanta en orden, cada cosa en su ventana:
    1. MySQL (XAMPP) ............ verifica puerto 3306, si está caído intenta arrancar mysql_start.bat
    2. API local ................ http://127.0.0.1:8000 (php -S + router del backend) si no responde
    3. Sync Agent ............... http://localhost:18650 (proxy + panel + sync cada 15 s)
    4. Web local ................ http://localhost:5173 (dist ya compilado con VITE_API_URL al agente)

  Uso:
    .\iniciar-ecoruta.ps1                  (todo)
    .\iniciar-ecoruta.ps1 -SinWeb           (sin frontend, solo API + agente)
    .\iniciar-ecoruta.ps1 -SinNavegador     (no abre el navegador)
    .\iniciar-ecoruta.ps1 -PuertoApi 8000 -XamppPath "C:\xampp"
#>
param(
  [int]$PuertoApi = 8000,
  [int]$PuertoAgente = 18650,
  [int]$PuertoWeb = 5173,
  [string]$XamppPath = "C:\xampp",
  [switch]$SinWeb,
  [switch]$SinNavegador
)

$ErrorActionPreference = 'Stop'
$Raiz = Split-Path -Parent $MyInvocation.MyCommand.Path
$BackendDir = Join-Path $Raiz 'backend'
$AgentDir = Join-Path $Raiz 'sync-agent'
$WebDir = Join-Path $Raiz 'frontend'

function Ok($m) { Write-Host "  [OK] $m" -ForegroundColor Green }
function Mal($m) { Write-Host "  [!!] $m" -ForegroundColor Red }
function Info($m) { Write-Host $m -ForegroundColor Cyan }

function PuertoAbierto($port) {
  try {
    $c = New-Object Net.Sockets.TcpClient
    $r = $c.BeginConnect('127.0.0.1', $port, $null, $null)
    $ok = $r.AsyncWaitHandle.WaitOne(800)
    $c.Close()
    return $ok
  } catch { return $false }
}

function EsperarHttp($url, $segundos = 25) {
  $fin = (Get-Date).AddSeconds($segundos)
  while ((Get-Date) -lt $fin) {
    try {
      $r = Invoke-WebRequest -Uri $url -TimeoutSec 4 -UseBasicParsing
      if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) { return $true }
    } catch { Start-Sleep -Seconds 1 }
  }
  return $false
}

function Necesita($cmd, $nombre) {
  if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
    Mal "Falta $nombre ($cmd) en el PATH. Instálalo y vuelve a correr."
    exit 1
  }
}

Info '== EcoRuta · Inicializador del sistema =='
Necesita 'php' 'PHP'; Necesita 'node' 'Node.js'; Necesita 'npm' 'npm'

# ---------- 0. Archivos .env ----------
Info '[0/4] Revisando configuración...'
$faltan = @()
foreach ($f in @("$BackendDir\.env", "$AgentDir\.env", "$WebDir\.env")) {
  if (-not (Test-Path $f)) { $faltan += $f }
}
if ($faltan.Count -gt 0) { Mal ("Faltan .env: " + ($faltan -join ', ')); exit 1 }
Ok 'backend/.env, sync-agent/.env y frontend/.env presentes.'

# ---------- 1. MySQL ----------
Info '[1/4] MySQL (XAMPP)...'
if (PuertoAbierto 3306) {
  Ok 'MySQL responde en 3306.'
} else {
  Mal 'MySQL no responde. Intentando arrancar XAMPP...'
  $mysqlStart = Join-Path $XamppPath 'mysql_start.bat'
  if (Test-Path $mysqlStart) { Start-Process -FilePath $mysqlStart -WorkingDirectory $XamppPath | Out-Null; Start-Sleep -Seconds 6 }
  if (PuertoAbierto 3306) { Ok 'MySQL arrancado.' }
  else { Mal 'No se pudo arrancar MySQL. Abre XAMPP Control manualmente y reintenta.'; exit 1 }
}

# ---------- 2. API local ----------
Info '[2/4] API local...'
$apiHealth = "http://127.0.0.1:$PuertoApi/api/health"
try {
  $h = Invoke-WebRequest -Uri $apiHealth -TimeoutSec 4 -UseBasicParsing | ConvertFrom-Json
  if ($h.status -eq 'ok') { Ok "API ya corriendo en :$PuertoApi." }
  else { throw 'respuesta inesperada' }
} catch {
  Info "  Levantando API en :$PuertoApi (ventana EcoRuta-API)..."
  if (-not (Test-Path "$BackendDir\public\router.php")) { Mal 'No se encontró backend/public/router.php'; exit 1 }
  Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', "title EcoRuta-API & php -S 127.0.0.1:$PuertoApi public\router.php" -WorkingDirectory $BackendDir | Out-Null
  if (EsperarHttp $apiHealth 25) { Ok "API arriba en :$PuertoApi." }
  else { Mal 'La API no respondió. Revisa la ventana EcoRuta-API.'; exit 1 }
}

# ---------- 3. Sync Agent ----------
Info '[3/4] Sync Agent...'
$agentStatus = "http://127.0.0.1:$PuertoAgente/agent/status"
$agentOk = $false
try { $agentOk = (Invoke-WebRequest -Uri $agentStatus -TimeoutSec 4 -UseBasicParsing).StatusCode -eq 200 } catch {}
if ($agentOk) {
  Ok "Agente ya corriendo en :$PuertoAgente."
} else {
  if (-not (Test-Path "$AgentDir\node_modules")) {
    Info '  Instalando dependencias del agente (una sola vez)...'
    Start-Process -FilePath 'npm' -ArgumentList 'install', '--no-audit', '--no-fund' -WorkingDirectory $AgentDir -Wait -NoNewWindow
  }
  Info "  Levantando agente en :$PuertoAgente (ventana EcoRuta-Sync)..."
  Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', 'title EcoRuta-Sync & node src\server.js' -WorkingDirectory $AgentDir | Out-Null
  if (EsperarHttp $agentStatus 25) { Ok "Agente arriba en :$PuertoAgente." }
  else { Mal 'El agente no respondió. Revisa la ventana EcoRuta-Sync.'; exit 1 }
}

# ---------- 4. Web local ----------
if (-not $SinWeb) {
  Info '[4/4] Web local...'
  $webUrl = "http://localhost:$PuertoWeb/"
  $webOk = $false
  try { $webOk = (Invoke-WebRequest -Uri $webUrl -TimeoutSec 4 -UseBasicParsing).StatusCode -eq 200 } catch {}
  if ($webOk) {
    Ok "Web ya corriendo en :$PuertoWeb."
  } else {
    if (-not (Test-Path "$WebDir\dist\index.html")) { Mal 'Falta frontend/dist. Corre `npm run build` en frontend/.'; exit 1 }
    Info "  Sirviendo dist en :$PuertoWeb (ventana EcoRuta-Web)..."
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', "title EcoRuta-Web & npx vite preview --port $PuertoWeb --strictPort" -WorkingDirectory $WebDir | Out-Null
    if (EsperarHttp $webUrl 30) { Ok "Web arriba en :$PuertoWeb." }
    else { Mal 'La web no respondió. Revisa la ventana EcoRuta-Web.'; exit 1 }
  }
} else {
  Info '[4/4] Web local omitida (-SinWeb).'
}

# ---------- Resumen ----------
Info ''
Info '== Todo arriba =='
Write-Host "  API ...... http://127.0.0.1:$PuertoApi/api/health" -ForegroundColor White
Write-Host "  Agente ... http://localhost:$PuertoAgente  (panel + proxy)" -ForegroundColor White
if (-not $SinWeb) { Write-Host "  Web ...... http://localhost:$PuertoWeb/" -ForegroundColor White }
Write-Host '  Para detener todo: correr detener-ecoruta.bat' -ForegroundColor DarkGray

if (-not $SinNavegador) {
  Start-Process "http://localhost:$PuertoAgente"
  if (-not $SinWeb) { Start-Sleep -Seconds 1; Start-Process "http://localhost:$PuertoWeb/" }
}
