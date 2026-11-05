@echo off
REM ============================================================
REM  Helpdesk Confipetrol - Queue Worker
REM ============================================================
REM
REM  Procesa la cola "database". En produccion lo lanza la tarea
REM  programada "Helpdesk - Queue Worker" (cada minuto, una sola
REM  instancia a la vez); en desarrollo se corre a mano en una CMD.
REM
REM  Uso:  queue-worker.bat ["C:\ruta\a\php.exe"]
REM        (sin argumento usa %PHP_BIN% o, si no existe, "php")
REM
REM  Banderas:
REM    --queue=default    procesa la cola por defecto
REM    --tries=3          reintenta hasta 3 veces antes de marcar failed
REM    --backoff=60       espera 60s entre reintentos
REM    --timeout=120      mata el job si pasa de 2 min
REM    --sleep=3          si la cola esta vacia, espera 3s antes de checar
REM    --max-time=3600    el worker termina cada hora (libera memoria)
REM    --max-jobs=1000    o tras procesar 1000 jobs, lo que ocurra primero
REM
REM  Al terminar (max-time/max-jobs o queue:restart), la tarea
REM  programada lo vuelve a lanzar en menos de un minuto.
REM ============================================================

cd /d "%~dp0\.."

if not "%~1"=="" set "PHP_BIN=%~1"
if not defined PHP_BIN set "PHP_BIN=php"

"%PHP_BIN%" artisan queue:work ^
    --queue=default ^
    --tries=3 ^
    --backoff=60 ^
    --timeout=120 ^
    --sleep=3 ^
    --max-time=3600 ^
    --max-jobs=1000

exit /b 0
