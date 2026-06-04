<?php

namespace App\Jobs;

use App\DTOs\KactusEmployee;
use App\Services\KactusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessKactusWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload  El cuerpo crudo del webhook de Kactus.
     */
    public function __construct(public array $payload) {}

    public function handle(KactusService $kactus): void
    {
        try {
            $emp = KactusEmployee::fromKactusPayload($this->payload);
            $kactus->syncToUser($emp);
        } catch (\Throwable $e) {
            Log::error('ProcessKactusWebhookJob falló', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);
            throw $e;
        }
    }
}
