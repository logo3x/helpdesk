<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

/**
 * Deja en la bitácora (log "acceso") cada inicio de sesión, cierre de
 * sesión e intento fallido, con IP y navegador. Cubre el login local
 * y el de Azure, porque ambos terminan en Auth::login().
 */
class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        $this->record('login', 'Inició sesión', $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->record('logout', 'Cerró sesión', $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->record('failed', 'Intento de inicio de sesión fallido', $event->user, [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function record(string $event, string $description, mixed $user, array $extra = []): void
    {
        $request = request();

        $logger = activity('acceso')
            ->event($event)
            ->withProperties(array_filter([
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                ...$extra,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($user instanceof Model) {
            $logger->causedBy($user)->performedOn($user);
        }

        $logger->log($description);
    }
}
