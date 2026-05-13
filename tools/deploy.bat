@echo off
REM ============================================================
REM  Helpdesk Confipetrol — Deploy completo (one-shot)
REM ============================================================
REM
REM  Uso (PowerShell o cmd, NO requiere Administrator):
REM    cd C:\inetpub\wwwroot\helpdesk
REM    tools\deploy.bat
REM
REM  Lo que hace:
REM    1. Verifica que estamos en la rama main y sin cambios locales.
REM    2. Hace git pull para traer el ultimo codigo del repo.
REM    3. Instala dependencias PHP (composer) sin dev y optimizadas.
REM    4. Instala dependencias JS (npm) y compila assets (Vite/Tailwind).
REM    5. Llama a after-deploy.bat para migraciones + caches + queue.
REM    6. Toca public/web.config para forzar a IIS a reload del App Pool
REM     (sin admin: appcmd recycle requiere privilegios elevados,
REM     pero tocar web.config es escritura normal sobre la carpeta).
REM
REM  Aborta inmediatamente si cualquier paso falla.
REM
REM  El usuario que corra este script solo necesita:
REM    - Permiso de lectura/escritura sobre la carpeta del proyecto.
REM    - PHP, Composer, npm y git en el PATH.
REM    - NO necesita ser Administrator del server.
REM ============================================================

setlocal EnableDelayedExpansion
cd /d "%~dp0\.."

set PHP_BIN=php
set COMPOSER_BIN=composer
set NPM_BIN=npm
set GIT_BIN=git

echo.
echo === [Paso 1/5] Verificando estado del repositorio ===

%GIT_BIN% rev-parse --abbrev-ref HEAD > "%TEMP%\helpdesk_branch.txt"
set /p CURRENT_BRANCH=<"%TEMP%\helpdesk_branch.txt"
del "%TEMP%\helpdesk_branch.txt"

if /I not "%CURRENT_BRANCH%"=="main" (
    echo.
    echo ERROR: Estas en la rama "%CURRENT_BRANCH%", no en "main".
    echo Cambia a main con: git checkout main
    exit /b 1
)

%GIT_BIN% diff-index --quiet HEAD --
if errorlevel 1 (
    echo.
    echo ERROR: Hay cambios sin commitear en el working tree.
    echo Resuelvelos antes de desplegar:
    %GIT_BIN% status --short
    exit /b 1
)

echo Rama main, working tree limpio. OK.

echo.
echo === [Paso 2/5] git pull origin main ===
%GIT_BIN% pull origin main
if errorlevel 1 (
    echo.
    echo ERROR: git pull fallo. Revisa la salida arriba.
    exit /b 1
)

echo.
echo === [Paso 3/5] composer install --no-dev --optimize-autoloader ===
%COMPOSER_BIN% install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 (
    echo.
    echo ERROR: composer install fallo.
    echo Verifica que PHP 8.5 esta instalado y las extensiones requeridas
    echo estan habilitadas: php -m
    exit /b 1
)

echo.
echo === [Paso 4/5] npm install + npm run build ===
call %NPM_BIN% install --no-audit --no-fund
if errorlevel 1 (
    echo.
    echo ERROR: npm install fallo.
    exit /b 1
)

call %NPM_BIN% run build
if errorlevel 1 (
    echo.
    echo ERROR: npm run build fallo. Verifica el manifest de Vite.
    exit /b 1
)

echo.
echo === [Paso 5/5] Migraciones + caches + queue (after-deploy.bat) ===
call "%~dp0after-deploy.bat"
if errorlevel 1 (
    echo.
    echo ERROR: after-deploy.bat fallo. Revisa la salida arriba.
    exit /b 1
)

echo.
echo === Forzando reload del App Pool ===
REM Tocar public\web.config cambia su mtime; IIS detecta el cambio y
REM recicla el App Pool sin necesidad de privilegios elevados (no es
REM 'appcmd recycle', es escritura normal sobre la carpeta del proyecto).
REM El truco 'copy /b file +,, file' actualiza la fecha sin alterar
REM el contenido del archivo.
if exist "public\web.config" (
    copy /b "public\web.config" +,, "public\web.config" >nul
    echo Reload del App Pool disparado vía web.config touch.
) else (
    echo ADVERTENCIA: public\web.config no existe. Crea uno o reinicia
    echo manualmente el App Pool desde IIS Manager.
)

echo.
echo ============================================================
echo  Deploy completado correctamente.
echo  Version desplegada:
%GIT_BIN% log --oneline -1
echo ============================================================

endlocal
