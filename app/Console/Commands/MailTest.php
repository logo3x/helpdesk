<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía un correo de prueba con el mailer configurado, para validar
 * la configuración (por ejemplo, Microsoft Graph) sin esperar a que
 * ocurra una notificación real.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to : Correo de destino}';

    protected $description = 'Envía un correo de prueba con el mailer configurado';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $mailer = (string) config('mail.default');

        $this->info("Enviando correo de prueba a {$to} con el mailer «{$mailer}»…");

        try {
            Mail::raw(
                "Este es un correo de prueba del Helpdesk Confipetrol.\n\nSi lo recibiste, el envío de notificaciones funciona correctamente.",
                fn ($message) => $message->to($to)->subject('Prueba de correo — Helpdesk Confipetrol'),
            );
        } catch (Throwable $e) {
            $this->error('Falló el envío: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Correo enviado. Revisa la bandeja de entrada (y la carpeta de spam).');

        return self::SUCCESS;
    }
}
