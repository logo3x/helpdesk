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
 * Se envía al autor del artículo cuando un supervisor lo aprueba y
 * publica. Cierra el loop del flujo de aprobación.
 */
class KbArticlePublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public KbArticle $article,
        public User $approvedBy,
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
        $by = $sanitize($this->approvedBy->name);

        return (new MailMessage)
            ->subject("Publicado: {$title}")
            ->greeting("Hola {$sanitize($notifiable->name)},")
            ->line("**{$by}** aprobó y publicó tu artículo:")
            ->line("**{$title}**")
            ->line('Ya está visible en el chatbot y en el centro de ayuda del portal.')
            ->action('Ver artículo', url("/portal/kb/{$this->article->slug}"));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Tu artículo fue publicado: {$this->article->title}")
            ->body("Aprobado por {$this->approvedBy->name}. Ya está visible en el chatbot y el centro de ayuda.")
            ->icon('heroicon-o-check-badge')
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label('Ver artículo')
                    ->url(url("/portal/kb/{$this->article->slug}"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
