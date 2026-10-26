<?php

use App\Models\User;
use App\Notifications\KactusSyncFailedNotification;
use Illuminate\Mail\MailManager;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    // Simula el SMTP rechazando el inicio de sesión (como con el MFA).
    $this->instance('mail.manager', new class(app()) extends MailManager
    {
        public function mailer($name = null)
        {
            throw new TransportException('535 5.7.3 Authentication unsuccessful');
        }
    });
    app()->forgetInstance(MailChannel::class);
    config(['queue.default' => 'sync']);
});

it('un fallo de correo no interrumpe la acción y queda en el log', function () {
    Log::spy();
    $user = User::factory()->create();

    $user->notify(new KactusSyncFailedNotification('Error de prueba'));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'MAIL_FAIL_SAFE')
            && str_contains($context['error'], '535'))
        ->once();
});

it('con MAIL_FAIL_SAFE desactivado el error se propaga', function () {
    config(['mail.fail_safe' => false]);
    $user = User::factory()->create();

    $user->notify(new KactusSyncFailedNotification('Error de prueba'));
})->throws(TransportException::class);
