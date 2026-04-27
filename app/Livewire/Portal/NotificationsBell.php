<?php

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Campanita de notificaciones en el header del portal del solicitante.
 *
 * Lee de la tabla `notifications` que ya alimentan las clases en
 * `app/Notifications/` (canal 'database') con shape Filament. Reusa los
 * mismos campos que escribe `BuildsFilamentDatabasePayload`:
 *
 *   - title
 *   - body
 *   - icon (heroicon)
 *   - iconColor (success | info | warning | danger | primary)
 *   - actions[0].url (a dónde lleva al hacer click)
 *
 * El polling se hace con wire:poll en la vista (cada 30s).
 */
class NotificationsBell extends Component
{
    /**
     * Marca una notificación como leída y, si tiene una URL en sus
     * actions, redirige ahí. Si no, simplemente actualiza el estado.
     */
    public function markAsReadAndGo(string $id): mixed
    {
        $notification = auth()->user()
            ?->unreadNotifications()
            ->whereKey($id)
            ->first();

        if ($notification === null) {
            // Puede haber sido marcada como leída desde otra pestaña;
            // intentamos buscarla incluso si ya está leída para tomar
            // su URL. Si no existe en absoluto, no hacemos nada.
            $notification = auth()->user()?->notifications()->whereKey($id)->first();
        }

        if ($notification === null) {
            return null;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $url = $this->extractUrl($notification);

        if ($url !== null) {
            return $this->redirect($url, navigate: true);
        }

        return null;
    }

    public function markAllAsRead(): void
    {
        auth()->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Recibe el evento del frontend (cuando el dropdown se abre) para
     * forzar el refresh de las notificaciones sin esperar al poll.
     */
    #[On('refresh-notifications')]
    public function refresh(): void
    {
        // Sin lógica adicional: render() se ejecuta y trae lo último.
    }

    public function render(): View
    {
        $user = auth()->user();

        $recent = $user?->notifications()
            ->latest()
            ->limit(10)
            ->get() ?? collect();

        return view('livewire.portal.notifications-bell', [
            'recent' => $recent,
            'unreadCount' => $user?->unreadNotifications()->count() ?? 0,
        ]);
    }

    /**
     * Extrae la URL del primer "action" del payload Filament. Soporta
     * notificaciones legacy (ticket_id, ticket_number) generando URL
     * fallback a /portal/tickets/{id} si fuera el caso.
     *
     * Las URLs se guardaron con el host vigente al momento de crear
     * la notif (ej. localhost:8000 si APP_URL apuntaba ahí). Si el
     * usuario cambia APP_URL después, las notifs viejas seguirían
     * apuntando al host viejo. Para evitarlo, descartamos el host
     * de la URL guardada y reconstruimos sobre el host actual.
     */
    protected function extractUrl(DatabaseNotification $notification): ?string
    {
        $data = $notification->data;

        if (! is_array($data)) {
            return null;
        }

        // Filament shape: data.actions[0].url
        $action = $data['actions'][0] ?? null;
        if (is_array($action) && ! empty($action['url'])) {
            return $this->rehostUrl($action['url']);
        }

        // Fallback: payload viejo con ticket_id (pre-v1.8).
        if (! empty($data['ticket_id'])) {
            return url('/portal/tickets/'.$data['ticket_id']);
        }

        return null;
    }

    /**
     * Toma una URL absoluta y la reconstruye contra el host actual,
     * preservando solo path + query + fragment. Esto neutraliza
     * cambios de APP_URL: las notifs viejas dejan de apuntar al
     * host viejo cacheado.
     */
    protected function rehostUrl(string $stored): string
    {
        $parts = parse_url($stored);

        if ($parts === false || empty($parts['path'])) {
            return $stored;
        }

        $path = $parts['path'];
        if (! empty($parts['query'])) {
            $path .= '?'.$parts['query'];
        }
        if (! empty($parts['fragment'])) {
            $path .= '#'.$parts['fragment'];
        }

        return url($path);
    }
}
