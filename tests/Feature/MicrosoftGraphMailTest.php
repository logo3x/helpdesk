<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Cache::flush();

    config([
        'mail.default' => 'graph',
        'mail.from.address' => 'notificacion.helpdeks@confipetrol.com',
        'mail.mailers.graph' => [
            'transport' => 'graph',
            'tenant_id' => 'tenant-123',
            'client_id' => 'client-abc',
            'client_secret' => 'secret-xyz',
            'sender' => 'notificacion.helpdeks@confipetrol.com',
        ],
    ]);
});

it('obtiene un token de aplicación y envía el correo por Graph como MIME', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(null, 202),
    ]);

    Mail::raw('Hola desde el Helpdesk', fn ($m) => $m->to('luis.oviedo@confipetrol.com')->subject('Prueba'));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'login.microsoftonline.com/tenant-123/oauth2/v2.0/token')
        && $request['grant_type'] === 'client_credentials'
        && $request['scope'] === 'https://graph.microsoft.com/.default');

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'graph.microsoft.com')) {
            return false;
        }

        $mime = base64_decode($request->body());

        return $request->url() === 'https://graph.microsoft.com/v1.0/users/notificacion.helpdeks%40confipetrol.com/sendMail'
            && $request->hasHeader('Authorization', 'Bearer token-1')
            && str_contains($mime, 'Subject: Prueba')
            && str_contains($mime, 'luis.oviedo@confipetrol.com')
            && str_contains($mime, 'Hola desde el Helpdesk');
    });
});

it('reutiliza el token en envíos seguidos', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(null, 202),
    ]);

    Mail::raw('Uno', fn ($m) => $m->to('a@confipetrol.com')->subject('1'));
    Mail::raw('Dos', fn ($m) => $m->to('b@confipetrol.com')->subject('2'));

    Http::assertSentCount(3);
});

it('lanza un error claro si Graph rechaza el envío', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(['error' => ['message' => 'Access is denied.']], 403),
    ]);

    Mail::raw('Hola', fn ($m) => $m->to('a@confipetrol.com')->subject('x'));
})->throws(TransportException::class, 'Access is denied.');

it('el comando mail:test informa si el envío falla', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error_description' => 'AADSTS7000215: Invalid client secret'], 401),
    ]);

    $this->artisan('mail:test', ['to' => 'luis.oviedo@confipetrol.com'])
        ->expectsOutputToContain('Invalid client secret')
        ->assertFailed();
});

it('se niega a enviar desde un remitente distinto al configurado', function () {
    Http::fake();

    Mail::raw('Hola', fn ($m) => $m->from('gerencia@confipetrol.com')->to('a@confipetrol.com')->subject('x'));
})->throws(TransportException::class, 'Remitente no autorizado');
