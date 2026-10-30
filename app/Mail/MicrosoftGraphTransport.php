<?php

namespace App\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Envía correos con Microsoft Graph (POST /users/{buzón}/sendMail) usando
 * credenciales de aplicación (client credentials).
 *
 * A diferencia de SMTP, no inicia sesión con usuario y contraseña, así
 * que no le afecta la doble autenticación (MFA) de Microsoft 365.
 * Requiere el permiso de aplicación Mail.Send con consentimiento de
 * administrador en el App Registration de Azure.
 *
 * El mensaje se envía como MIME completo, así que HTML, adjuntos y
 * encabezados se conservan tal cual los arma Laravel.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        protected string $tenantId,
        protected string $clientId,
        protected string $clientSecret,
        protected string $sender,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        // Candado de seguridad: el permiso Mail.Send de Azure permitiría
        // enviar como cualquier buzón de la empresa; aquí solo se acepta
        // el remitente configurado.
        $from = strtolower($message->getEnvelope()->getSender()->getAddress());

        if ($from !== strtolower($this->sender)) {
            throw new TransportException(sprintf(
                'Remitente no autorizado para Microsoft Graph: %s (solo se permite %s).',
                $from,
                $this->sender,
            ));
        }

        $response = Http::withToken($this->accessToken())
            ->withBody(base64_encode($message->toString()), 'text/plain')
            ->timeout(30)
            ->post('https://graph.microsoft.com/v1.0/users/'.rawurlencode($this->sender).'/sendMail');

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        if (! $response->successful()) {
            throw new TransportException(sprintf(
                'Microsoft Graph rechazó el envío (HTTP %d): %s',
                $response->status(),
                $response->json('error.message') ?? $response->body(),
            ));
        }
    }

    /**
     * Token de aplicación, cacheado hasta un minuto antes de que expire.
     */
    protected function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout(30)
            ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new TransportException(sprintf(
                'No se pudo obtener el token de Microsoft Graph (HTTP %d): %s',
                $response->status(),
                $response->json('error_description') ?? $response->body(),
            ));
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 60);

        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    protected function tokenCacheKey(): string
    {
        return 'mail-graph-token:'.md5($this->tenantId.'|'.$this->clientId);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
