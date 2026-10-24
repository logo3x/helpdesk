# Install templates — Flujo Git-first con Claude multi-dispositivo
#
# Uso desde PowerShell en la raíz del proyecto nuevo:
#   iwr https://raw.githubusercontent.com/logo3x/helpdesk/main/docs/templates/install.ps1 -UseBasicParsing | iex
#
# O descarga + ejecución manual:
#   .\install.ps1 -ProjectName "Legalweb Confipetrol" -Version "1.0"

param(
    [string]$ProjectName = "Mi Proyecto",
    [string]$Version = "1.0",
    [string]$Stack = "Laravel 13 + Livewire 4",
    [string]$Usuario = "Luis Oviedo <luis.oviedo@confipetrol.com>",
    [switch]$Force
)

$ErrorActionPreference = "Stop"
$BaseUrl = "https://raw.githubusercontent.com/logo3x/helpdesk/main/docs/templates"
$Fecha = Get-Date -Format "yyyy-MM-dd"

Write-Host "Instalando templates Git-first para '$ProjectName'..." -ForegroundColor Cyan

# Verificar que estamos en un repo git
if (-not (Test-Path ".git")) {
    Write-Warning "No se detectó un repo git. ¿Correr 'git init' primero?"
    $continue = Read-Host "¿Continuar igualmente? (s/N)"
    if ($continue -ne "s") { exit }
}

# Crear carpeta docs si no existe
if (-not (Test-Path "docs")) {
    New-Item -ItemType Directory -Path "docs" | Out-Null
    Write-Host "✓ Carpeta docs/ creada" -ForegroundColor Green
}

# Función de descarga con reemplazo de placeholders
function Install-Template {
    param($Source, $Target)

    if ((Test-Path $Target) -and (-not $Force)) {
        Write-Warning "$Target ya existe. Usar -Force para sobrescribir."
        return
    }

    $content = Invoke-WebRequest -Uri "$BaseUrl/$Source" -UseBasicParsing | Select-Object -ExpandProperty Content

    # Reemplazos
    $content = $content -replace '\{\{PROYECTO\}\}', $ProjectName
    $content = $content -replace '\{\{STACK\}\}', $Stack
    $content = $content -replace '\{\{VERSION\}\}', $Version
    $content = $content -replace '\{\{FECHA\}\}', $Fecha
    $content = $content -replace '\{\{USUARIO_PRINCIPAL\}\}', $Usuario

    Set-Content -Path $Target -Value $content -Encoding UTF8
    Write-Host "✓ $Target" -ForegroundColor Green
}

Install-Template "CLAUDE.md.template" "CLAUDE.md"
Install-Template "pendientes.md.template" "docs/pendientes.md"
Install-Template "plan-version.md.template" "docs/plan-v$Version.md"

Write-Host ""
Write-Host "Listo. Próximos pasos:" -ForegroundColor Cyan
Write-Host "  1. Editar CLAUDE.md y reemplazar placeholders restantes (busca '{{')"
Write-Host "  2. Editar docs/pendientes.md con tu backlog real"
Write-Host "  3. git add CLAUDE.md docs/ && git commit -m 'docs: setup flujo Git-first'"
Write-Host "  4. git push"
Write-Host ""
Write-Host "Desde ahora podés trabajar desde PC, web o móvil usando el repo como estado compartido." -ForegroundColor Yellow
