@echo off
REM ============================================================
REM  Helpdesk Confipetrol — Queue Worker (DESARROLLO)
REM ============================================================
REM
REM  Igual que queue-worker.bat pero pensado para correr a mano
REM  en una ventana CMD/PowerShell durante desarrollo:
REM    - intervalo más corto (--sleep=1) para feedback instantáneo
REM    - sin max-time (mantiene la ventana abierta indefinidamente)
REM    - imprime info de cada job en colores (--verbose en Laravel default)
REM
REM  Cómo usar:
REM    Doble click en este archivo, o desde CMD:
REM      .\tools\queue-worker-dev.bat
REM
REM  Para detenerlo: Ctrl+C en la ventana.
REM ============================================================

cd /d "%~dp0\.."

set PHP_BIN=php

echo.
echo === Helpdesk Confipetrol — Queue Worker ===
echo Procesando cola "default" (Ctrl+C para detener)
echo.

%PHP_BIN% artisan queue:work ^
    --queue=default ^
    --tries=3 ^
    --backoff=10 ^
    --timeout=120 ^
    --sleep=1 ^
    --verbose

pause
