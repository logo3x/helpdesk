<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKactusWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint que Kactus llama cuando crea/actualiza/baja un empleado.
 *
 * Seguridad: header `X-Kactus-Signature` con HMAC-SHA256 del body
 * crudo, usando `kactus.webhook_secret` como clave. Verificamos con
 * hash_equals() para evitar timing attacks. El sync real corre en
 * cola (ProcessKactusWebhookJob) para responder rápido y permitir
 * retry transparente.
 */
class KactusWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! config('kactus.enabled')) {
            return response()->json(['error' => 'integration disabled'], 503);
        }

        $secret = (string) config('kactus.webhook_secret');
        if ($secret === '') {
            Log::warning('Webhook Kactus llamado pero KACTUS_WEBHOOK_SECRET no configurado.');

            return response()->json(['error' => 'webhook not configured'], 503);
        }

        $signature = (string) $request->header('X-Kactus-Signature');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('Webhook Kactus con firma inválida', ['ip' => $request->ip()]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['error' => 'empty payload'], 422);
        }

        ProcessKactusWebhookJob::dispatch($payload);

        return response()->json(['status' => 'queued'], 202);
    }
}
