@echo off
REM ============================================================
REM  Helpdesk Confipetrol — Post-deploy
REM ============================================================
REM
REM  Ejecutar después de cada deploy en producción para:
REM    1. Limpiar caches
REM    2. Aplicar migraciones pendientes
REM    3. Reconstruir assets de Filament
REM    4. Pedir al worker que se reinicie tras el job actual
REM       (NSSM lo reiniciará automáticamente, sin perder nada)
REM ============================================================

cd /d "%~dp0\.."

set PHP_BIN=php

echo [1/5] Limpiando caches...
%PHP_BIN% artisan optimize:clear

echo.
echo [2/5] Aplicando migraciones pendientes...
%PHP_BIN% artisan migrate --force --no-interaction

echo.
echo [3/5] Reconstruyendo cache de configuracion y rutas...
%PHP_BIN% artisan config:cache
%PHP_BIN% artisan route:cache
%PHP_BIN% artisan view:cache

echo.
echo [4/5] Limpiando cache de componentes Filament...
%PHP_BIN% artisan filament:clear-cached-components

echo.
echo [5/5] Senalando al worker para que se reinicie tras el job actual...
%PHP_BIN% artisan queue:restart

echo.
echo === Deploy completo ===
echo NSSM reiniciara el worker automaticamente con el codigo nuevo.
