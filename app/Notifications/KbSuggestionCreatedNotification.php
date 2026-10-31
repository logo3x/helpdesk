<?php

namespace App\Notifications;

use App\Models\KbArticle;
use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a supervisores del departamento y administradores que la IA
 * propuso un artículo KB nuevo a partir de consultas sin respuesta.
 */
class KbSuggestionCreatedNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
    use Queueable;

    public function __construct(public KbArticle $article) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sanitize = fn (?string $s): string => trim(strip_tags((string) $s));
        $context = $this->article->suggestion_context ?? [];

        $mail = (new MailMessage)
            ->subject("Sugerencia de artículo KB: {$sanitize($this->article->title)}")
            ->greeting("Hola {$sanitize($notifiable->name)},")
            ->line('El asistente detectó preguntas de los empleados que la base de conocimiento no responde y redactó un borrador:')
            ->line("**{$sanitize($this->article->title)}**")
            ->line('Departamento: '.($sanitize($this->article->department?->name) ?: 'sin asignar'));

        if (filled($context['reason'] ?? null)) {
            $mail->line('Motivo: '.$sanitize($context['reason']));
        }

        if (filled($context['queries'] ?? null)) {
            $mail->line('Preguntas de ejemplo: «'.implode('», «', array_map($sanitize, array_slice($context['queries'], 0, 3))).'»');
        }

        return $mail
            ->action('Revisar borrador', url("/soporte/kb-articles/{$this->article->id}/edit"))
            ->line('Revisa y corrige el contenido (la IA no conoce los procedimientos internos exactos). Si sirve, usa "Aprobar y publicar"; si no, elimínalo.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title("Sugerencia KB: {$this->article->title}")
            ->body('La IA redactó este borrador a partir de preguntas sin respuesta del chatbot. Revísalo antes de publicar.')
            ->icon('heroicon-o-light-bulb')
            ->iconColor('info')
            ->actions([
                Action::make('review')
                    ->label('Revisar borrador')
                    ->url(url("/soporte/kb-articles/{$this->article->id}/edit"))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
