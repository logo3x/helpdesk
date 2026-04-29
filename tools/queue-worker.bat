@echo off
REM ============================================================
REM  Helpdesk Confipetrol — Queue Worker
REM ============================================================
REM
REM  Procesa la cola "database" indefinidamente.
REM  Se ejecuta como servicio de Windows via NSSM en producción
REM  y manualmente desde una ventana CMD en desarrollo.
REM
REM  Banderas:
REM    --queue=default    procesa la cola por defecto
REM    --tries=3          reintenta hasta 3 veces antes de marcar failed
REM    --backoff=60       espera 60s entre reintentos
REM    --timeout=120      mata el job si pasa de 2 min
REM    --sleep=3          si la cola está vacía, espera 3s antes de checar
REM    --max-time=3600    el worker se reinicia cada hora (libera memoria)
REM    --max-jobs=1000    o tras procesar 1000 jobs, lo que ocurra primero
REM
REM  Importante: NSSM detecta la salida del proceso y lo reinicia.
REM  El "--max-time" hace ese reinicio "limpio" cada hora para evitar
REM  memory leaks de PHP en procesos largos.
REM ============================================================

cd /d "%~dp0\.."

REM Ajusta la ruta a php.exe si no está en el PATH del sistema.
REM Para Wampserver típicamente: C:\wamp64\bin\php\php8.5.0\php.exe
set PHP_BIN=php

%PHP_BIN% artisan queue:work ^
    --queue=default ^
    --tries=3 ^
    --backoff=60 ^
    --timeout=120 ^
    --sleep=3 ^
    --max-time=3600 ^
    --max-jobs=1000

REM Si llegó aquí, el worker terminó (max-time/max-jobs alcanzado).
REM NSSM lo reiniciará automáticamente.
exit /b 0
