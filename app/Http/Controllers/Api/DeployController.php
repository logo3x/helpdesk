<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Webhook de deploy para actualizar el server sin necesidad de SSH/RDP.
 *
 * Diseño:
 *  - El token compartido (DEPLOY_TOKEN en .env) autentica el llamador.
 *  - El endpoint dispara la tarea programada "HelpdeskDeploy" que un
 *    administrador ya configuró con `schtasks` (corre como el user que
 *    tiene permisos de git pull + escritura sobre el proyecto).
 *  - La tarea ejecuta tools\deploy-remote.bat que escribe a
 *    storage/logs/deploy.log + un marcador [DEPLOY-DONE] o
 *    [DEPLOY-FAILED] al terminar.
 *  - El controller polea el log y devuelve el resultado al cliente.
 *
 * Seguridad:
 *  - Comparación con hash_equals (timing-attack safe).
 *  - Lock de cache de 10 min para evitar deploys concurrentes.
 *  - Rate-limited por la ruta misma (5 reqs/min — más que suficiente).
 *  - El token vive solo en .env del server (nunca en repo).
 */
class DeployController extends Controller
{
    /**
     * Cuánto esperar a que termine el deploy antes de devolver "running".
     * El cliente puede llamar a `log()` para ver el progreso después.
     */
    protected const WAIT_SECONDS = 180;

    /**
     * Intervalo de polling del log file mientras esperamos.
     */
    protected const POLL_SECONDS = 2;

    /**
     * POST /api/deploy?token=XXX
     *
     * Dispara el deploy y espera hasta WAIT_SECONDS segundos a que
     * termine. Si termina dentro del timeout, devuelve el resultado;
     * si no, devuelve "running" con el log parcial — el cliente puede
     * polear GET /api/deploy/log?token=XXX para ver el progreso.
     */
    public function trigger(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $lock = Cache::lock('deploy:running', 600);

        if (! $lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Ya hay un deploy en curso. Llamá /api/deploy/log para ver el progreso.',
                'log' => $this->readLog(),
            ], 409);
        }

        try {
            $this->resetLog();

            $taskResult = Process::run('schtasks /run /tn "HelpdeskDeploy"');

            if (! $taskResult->successful()) {
                return response()->json([
                    'status' => 'failed_to_start',
                    'message' => 'schtasks /run falló — verificá que la tarea "HelpdeskDeploy" exista y el usuario que corre el App Pool de IIS tenga permiso para dispararla.',
                    'stderr' => $taskResult->errorOutput(),
                    'stdout' => $taskResult->output(),
                ], 500);
            }

            // Polling del log hasta ver el marker final o agotar el tiempo.
            $deadline = microtime(true) + self::WAIT_SECONDS;
            $finalStatus = null;

            while (microtime(true) < $deadline) {
                $log = $this->readLog();

                if (str_contains($log, '[DEPLOY-DONE]')) {
                    $finalStatus = 'ok';
                    break;
                }
                if (str_contains($log, '[DEPLOY-FAILED]')) {
                    $finalStatus = 'failed';
                    break;
                }

                sleep(self::POLL_SECONDS);
            }

            return response()->json([
                'status' => $finalStatus ?? 'running',
                'log' => $this->readLog(),
                'started_at' => now()->toIso8601String(),
            ], $finalStatus === 'failed' ? 500 : 200);
        } finally {
            $lock->release();
        }
    }

    /**
     * GET /api/deploy/log?token=XXX
     *
     * Devuelve el contenido del log de deploy. Útil para hacer polling
     * cuando el endpoint trigger() devolvió "running" porque el deploy
     * se está demorando más de WAIT_SECONDS.
     */
    public function log(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $log = $this->readLog();

        $status = match (true) {
            str_contains($log, '[DEPLOY-DONE]') => 'ok',
            str_contains($log, '[DEPLOY-FAILED]') => 'failed',
            $log !== '' => 'running',
            default => 'idle',
        };

        return response()->json([
            'status' => $status,
            'log' => $log,
        ]);
    }

    protected function tokenIsValid(Request $request): bool
    {
        $expected = (string) config('deploy.token', '');
        $given = (string) $request->query('token', '');

        if ($expected === '' || $given === '') {
            return false;
        }

        return hash_equals($expected, $given);
    }

    protected function logPath(): string
    {
        return storage_path('logs/deploy.log');
    }

    protected function readLog(): string
    {
        $path = $this->logPath();

        if (! is_file($path)) {
            return '';
        }

        return (string) @file_get_contents($path);
    }

    protected function resetLog(): void
    {
        $dir = dirname($this->logPath());
        if (! is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        @file_put_contents(
            $this->logPath(),
            '[DEPLOY-WAITING] '.now()->toIso8601String()."\n",
        );
    }
}
