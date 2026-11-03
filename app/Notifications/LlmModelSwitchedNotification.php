<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa a los administradores que el sistema cambió solo de modelo de IA
 * (porque el anterior dejó de responder o porque el preferido volvió),
 * o que ningún modelo responde ($current = null).
 */
class LlmModelSwitchedNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
    use Queueable;

    public function __construct(
        public string $previous,
        public ?string $current,
        public string $reason,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->current
                ? 'Asistente IA: cambio automático de modelo'
                : '⚠️ Asistente IA sin servicio')
            ->greeting("Hola {$notifiable->name},");

        if ($this->current) {
            $mail->line("El asistente virtual cambió automáticamente de modelo de IA: **{$this->previous}** → **{$this->current}**.")
                ->line($this->reason)
                ->line('No necesitas hacer nada. Si quieres fijar este modelo, cambia LLM_MODEL en el .env del servidor.');
        } else {
            $mail->line("El modelo **{$this->previous}** no responde y no se encontró otro que funcione.")
                ->line($this->reason)
                ->line('Mientras tanto, el asistente responde con los artículos completos de la base de conocimiento. Revisa el servicio en Métricas del chatbot → "Probar conexión con la IA".');
        }

        return $mail->action('Ver métricas del chatbot', url('/admin/chatbot-metrics'));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->current ? 'IA: cambio automático de modelo' : 'IA sin servicio')
            ->body($this->current
                ? "{$this->previous} → {$this->current}. {$this->reason}"
                : "Ningún modelo responde. {$this->reason}")
            ->icon($this->current ? 'heroicon-o-arrow-path' : 'heroicon-o-exclamation-triangle')
            ->iconColor($this->current ? 'info' : 'danger')
            ->actions([
                Action::make('metrics')
                    ->label('Ver métricas')
                    ->url(url('/admin/chatbot-metrics'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
