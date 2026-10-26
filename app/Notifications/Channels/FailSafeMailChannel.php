<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * TEMPORAL — red de seguridad mientras se migra el envío de correo a
 * Microsoft Graph (el SMTP quedó bloqueado por el MFA de Microsoft 365).
 *
 * Si el envío de un correo de notificación falla, lo registra en el log
 * y deja que la acción del usuario continúe (comentar, crear ticket,
 * programar mantenimiento…) en lugar de mostrar un error 500.
 * La notificación en la campanita se guarda igual.
 *
 * Se controla con MAIL_FAIL_SAFE en el .env (true por defecto). Para
 * quitarlo: borrar esta clase y su registro en AppServiceProvider.
 * Con la cola en modo `database` conviene desactivarlo, para que los
 * envíos fallidos se reintenten.
 */
class FailSafeMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (TransportExceptionInterface $e) {
            if (! config('mail.fail_safe', true)) {
                throw $e;
            }

            Log::warning('Correo de notificación no enviado (MAIL_FAIL_SAFE activo).', [
                'notification' => $notification::class,
                'notifiable' => $notifiable::class.'#'.($notifiable->getKey() ?? '?'),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
