<?php

namespace App\Notifications;

use App\Models\KbArticle;
use App\Models\User;
use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía a los supervisores del depto del artículo cuando un agente
 * solicita publicación. El supervisor lo recibe en su campanita de
 * /soporte (databaseNotifications) y por correo.
 */
class KbArticleReviewRequestedNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
    use Queueable;

    public function __construct(
        public KbArticle $article,
        public User $requestedBy,
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
        $by = $sanitize($this->requestedBy->name);

        return (new MailMessage)
            ->subject("Revisión solicitada: {$title}")
            ->greeting("Hola {$sanitize($notifiable->name)},")
            ->line("**{$by}** solicita tu aprobación para publicar el artículo de KB:")
            ->line("**{$title}**")
            ->action('Revisar artículo', url("/soporte/kb-articles/{$this->article->id}/edit"))
            ->line('Si el contenido está correcto, usa el botón "Aprobar y publicar". Si necesita cambios, devuélvelo a borrador y coordina con el autor.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Revisión KB: {$this->article->title}")
            ->body("{$this->requestedBy->name} solicita aprobación para publicar este artículo.")
            ->icon('heroicon-o-document-magnifying-glass')
            ->iconColor('warning')
            ->actions([
                Action::make('review')
                    ->label('Revisar artículo')
                    ->url(url("/soporte/kb-articles/{$this->article->id}/edit"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
