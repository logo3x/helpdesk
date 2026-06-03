<?php

namespace App\Notifications;

use App\Models\KbArticle;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía al autor cuando un supervisor rechaza la solicitud de
 * publicación con un motivo. El artículo vuelve a draft puro y el
 * autor puede corregir y solicitar nuevamente.
 */
class KbArticleRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public KbArticle $article,
        public User $rejectedBy,
        public string $reason,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sanitize = fn (?string $s): string => trim(strip_tags((string) $s));

        $title = $sanitize($this->article->title);
        $by = $sanitize($this->rejectedBy->name);
        $reason = $sanitize($this->reason);

        return (new MailMessage)
            ->subject("Rechazado: {$title}")
            ->greeting("Hola {$sanitize($notifiable->name)},")
            ->line("**{$by}** revisó tu artículo y solicitó cambios antes de publicar:")
            ->line("**{$title}**")
            ->line('**Motivo:** '.$reason)
            ->line('El artículo está en Borrador. Edita y vuelve a "Solicitar publicación" cuando quieras.')
            ->action('Editar artículo', url("/soporte/kb-articles/{$this->article->id}/edit"));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Tu artículo necesita cambios: {$this->article->title}")
            ->body("Revisado por {$this->rejectedBy->name}. Motivo: {$this->reason}")
            ->icon('heroicon-o-x-circle')
            ->iconColor('warning')
            ->actions([
                Action::make('edit')
                    ->label('Editar artículo')
                    ->url(url("/soporte/kb-articles/{$this->article->id}/edit"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
