<#
.SYNOPSIS
    Helpdesk Confipetrol — Setup del usuario `deploy-helpdesk` con SSH.

.DESCRIPTION
    Crea un usuario local SIN privilegios de Administrator que solo puede:
      - Leer y escribir en C:\inetpub\wwwroot\helpdesk
      - Conectarse via SSH (OpenSSH Server de Windows)
      - Ejecutar deploy.bat (que no requiere admin tras el cambio del v1.17.2)

    NO puede:
      - Modificar otros sitios IIS, registros, servicios.
      - Instalar software, cambiar config del sistema.
      - Ver carpetas de otros usuarios.

    Ejecutar en el server, PowerShell como Administrator, UNA sola vez.

.NOTES
    Después de correr este script:
      1. Pegá tu llave pública SSH (id_ed25519.pub o id_rsa.pub de tu
         máquina local) en C:\Users\deploy-helpdesk\.ssh\authorized_keys
      2. Probá desde tu local: ssh deploy-helpdesk@<server>
      3. Una vez dentro, corré: cd C:\inetpub\wwwroot\helpdesk; tools\deploy.bat
#>
[CmdletBinding()]
param(
    [string]$Username = 'deploy-helpdesk',
    [string]$ProjectPath = 'C:\inetpub\wwwroot\helpdesk',
    [SecureString]$Password
)

$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)
    Write-Host "==> $Message" -ForegroundColor Cyan
}

# ─── Validaciones de entorno ────────────────────────────────────

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Error "Este script requiere PowerShell como Administrator."
    exit 1
}

if (-not (Test-Path $ProjectPath)) {
    Write-Error "No existe $ProjectPath — instalá el proyecto primero."
    exit 1
}

# ─── 1. Crear el usuario local ──────────────────────────────────

Write-Step "Creando usuario local '$Username'..."

if (Get-LocalUser -Name $Username -ErrorAction SilentlyContinue) {
    Write-Host "    Usuario ya existe, salteamos creación." -ForegroundColor Yellow
} else {
    if (-not $Password) {
        $Password = Read-Host "Definí una contraseña inicial para '$Username' (después usás SSH key)" -AsSecureString
    }

    New-LocalUser -Name $Username `
        -Password $Password `
        -FullName "Helpdesk Deploy User" `
        -Description "Cuenta limitada para correr deploy del helpdesk via SSH" `
        -PasswordNeverExpires `
        -UserMayNotChangePassword | Out-Null

    Write-Host "    Usuario creado." -ForegroundColor Green
}

# Asegurar que NO es Administrator local.
if ((Get-LocalGroupMember -Group "Administrators" -ErrorAction SilentlyContinue | Where-Object { $_.Name -like "*\$Username" })) {
    Write-Step "Quitando '$Username' del grupo Administrators (no debe estar ahí)..."
    Remove-LocalGroupMember -Group "Administrators" -Member $Username
}

# ─── 2. Permisos NTFS solo sobre la carpeta del proyecto ────────

Write-Step "Aplicando permisos NTFS sobre $ProjectPath..."
icacls $ProjectPath /grant "${Username}:(OI)(CI)M" /T | Out-Null
Write-Host "    Modify (lectura + escritura) concedido recursivamente." -ForegroundColor Green

# ─── 3. Permitir login interactivo / SSH ────────────────────────

Write-Step "Asegurando que '$Username' puede loguearse por SSH..."
# Por default Windows permite SSH a cualquier user válido, pero por las
# dudas verificamos que el grupo "Users" lo contenga.
if (-not (Get-LocalGroupMember -Group "Users" -ErrorAction SilentlyContinue | Where-Object { $_.Name -like "*\$Username" })) {
    Add-LocalGroupMember -Group "Users" -Member $Username
    Write-Host "    Agregado al grupo Users." -ForegroundColor Green
} else {
    Write-Host "    Ya está en el grupo Users." -ForegroundColor Yellow
}

# ─── 4. OpenSSH Server instalado y corriendo ────────────────────

Write-Step "Verificando OpenSSH Server..."
$openssh = Get-WindowsCapability -Online -Name 'OpenSSH.Server*' | Select-Object -First 1
if ($openssh.State -ne 'Installed') {
    Write-Host "    Instalando OpenSSH Server..." -ForegroundColor Yellow
    Add-WindowsCapability -Online -Name OpenSSH.Server~~~~0.0.1.0 | Out-Null
}

Start-Service sshd -ErrorAction SilentlyContinue
Set-Service -Name sshd -StartupType 'Automatic'

if (-not (Get-NetFirewallRule -Name "OpenSSH-Server-In-TCP" -ErrorAction SilentlyContinue)) {
    Write-Host "    Creando regla de firewall para puerto 22..." -ForegroundColor Yellow
    New-NetFirewallRule -Name 'OpenSSH-Server-In-TCP' `
      -DisplayName 'OpenSSH SSH Server' `
      -Enabled True -Direction Inbound -Protocol TCP -Action Allow -LocalPort 22 | Out-Null
}

Write-Host "    OpenSSH Server activo." -ForegroundColor Green

# ─── 5. Crear directorio .ssh del usuario (para authorized_keys) ─

$sshDir = "C:\Users\$Username\.ssh"
Write-Step "Preparando $sshDir para autenticación por llave..."

# El home del user solo existe tras el primer login. Lo creamos a mano
# para poder dejarle el authorized_keys listo desde el principio.
if (-not (Test-Path "C:\Users\$Username")) {
    New-Item -ItemType Directory -Path "C:\Users\$Username" -Force | Out-Null
    icacls "C:\Users\$Username" /grant "${Username}:(OI)(CI)F" | Out-Null
}

if (-not (Test-Path $sshDir)) {
    New-Item -ItemType Directory -Path $sshDir -Force | Out-Null
}

$authKeysFile = Join-Path $sshDir 'authorized_keys'
if (-not (Test-Path $authKeysFile)) {
    New-Item -ItemType File -Path $authKeysFile -Force | Out-Null
}

# Permisos restrictivos sobre .ssh y authorized_keys (sshd los exige).
icacls $sshDir /inheritance:r /grant "${Username}:(OI)(CI)F" /grant "SYSTEM:(OI)(CI)F" | Out-Null
icacls $authKeysFile /inheritance:r /grant "${Username}:F" /grant "SYSTEM:F" | Out-Null

Write-Host "    $authKeysFile listo para que pegues tu llave pública." -ForegroundColor Green

# ─── 6. Resumen ─────────────────────────────────────────────────

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host " Setup completado." -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host ""
Write-Host " Usuario:        $Username (NO admin)"
Write-Host " Permisos NTFS:  Modify recursivo sobre $ProjectPath"
Write-Host " SSH:            Habilitado en puerto 22"
Write-Host " authorized_keys: $authKeysFile"
Write-Host ""
Write-Host " Próximos pasos:" -ForegroundColor Yellow
Write-Host "  1. En tu máquina local, generá una llave si no tenés:"
Write-Host "       ssh-keygen -t ed25519 -C 'deploy-helpdesk'"
Write-Host "  2. Copiá el contenido de ~/.ssh/id_ed25519.pub a:"
Write-Host "       $authKeysFile"
Write-Host "  3. Probá la conexión:"
Write-Host "       ssh $Username@<server-host>"
Write-Host "  4. Una vez dentro:"
Write-Host "       cd $ProjectPath"
Write-Host "       tools\deploy.bat"
Write-Host ""
Write-Host " Para deshabilitar password (recomendado, solo llaves):" -ForegroundColor Yellow
Write-Host "    Editá C:\ProgramData\ssh\sshd_config:"
Write-Host "      PasswordAuthentication no"
Write-Host "      PubkeyAuthentication yes"
Write-Host "    Restart-Service sshd"
