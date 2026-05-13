@echo off
REM ============================================================
REM  Helpdesk Confipetrol — Deploy remoto (via webhook)
REM ============================================================
REM
REM  Wrapper alrededor de deploy.bat que:
REM    1. Reset del archivo storage\logs\deploy.log
REM    2. Llama a deploy.bat capturando TODO el output a ese log
REM    3. Al final escribe un marcador [DEPLOY-DONE] o [DEPLOY-FAILED]
REM       que el webhook lee para saber si el deploy terminó
REM       (success o falla) y devolver el resultado al cliente.
REM
REM  Diseñado para correrse desde Task Scheduler como adminit:
REM    schtasks /create /tn "HelpdeskDeploy" ^
REM      /tr "C:\inetpub\wwwroot\helpdesk\tools\deploy-remote.bat" ^
REM      /sc ONDEMAND /ru adminit /rp /it
REM
REM  No corras este script directamente desde una sesión interactiva;
REM  para eso usá tools\deploy.bat que mantiene el output en pantalla.
REM ============================================================

setlocal
cd /d "%~dp0\.."

set LOG_DIR=%~dp0..\storage\logs
set LOG_FILE=%LOG_DIR%\deploy.log

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

REM Marker de inicio + timestamp ANTES del reset, para que el cliente
REM siempre vea al menos la línea de "started" mientras corre el deploy.
> "%LOG_FILE%" (
    echo [DEPLOY-START] %DATE% %TIME%
)

REM Llamada al deploy.bat capturando stdout + stderr al log.
call "%~dp0deploy.bat" >> "%LOG_FILE%" 2>&1
set DEPLOY_RC=%errorlevel%

if %DEPLOY_RC% EQU 0 (
    echo. >> "%LOG_FILE%"
    echo [DEPLOY-DONE] %DATE% %TIME% >> "%LOG_FILE%"
) else (
    echo. >> "%LOG_FILE%"
    echo [DEPLOY-FAILED] code=%DEPLOY_RC% at %DATE% %TIME% >> "%LOG_FILE%"
)

exit /b %DEPLOY_RC%
