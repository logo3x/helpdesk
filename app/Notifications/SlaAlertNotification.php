<?php

namespace App\Notifications;

use App\Models\EscalationLog;
use App\Models\Ticket;
use App\Notifications\Concerns\BuildsFilamentDatabasePayload;
use App\Services\KbSuggestionService;
use App\Services\SlaService;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de una alerta SLA (70 %, 90 % o incumplimiento) registrada por
 * SlaService::checkBreaches(). Los canales de cada nivel se configuran en
 * config('tickets.sla_alerts.channels'); la campanita siempre se envía.
 */
class SlaAlertNotification extends Notification implements ShouldQueue
{
    use BuildsFilamentDatabasePayload;
    use Queueable;

    public function __construct(public EscalationLog $log) {}

    /**
     * Si el ticket se eliminó, o se cerró, entre el registro de la alerta
     * y el envío (la notificación va por la cola), el aviso ya no aplica.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $ticket = $this->log->ticket;

        return $ticket !== null && $ticket->status?->isOpen() === true;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $configured = (array) config("tickets.sla_alerts.channels.{$this->log->alertLevel()}", []);

        return in_array('mail', $configured, true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket();

        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hola '.KbSuggestionService::plainText($notifiable->name).',')
            ->line($this->log->isBreach()
                ? 'Este ticket superó el plazo del SLA:'
                : "Este ticket ya consumió el {$this->log->thresholdPercent()} % del plazo del SLA:")
            ->line("**{$ticket->number}** — {$this->subject()}");

        foreach ($this->details() as $label => $value) {
            $mail->line("{$label}: {$value}");
        }

        return $mail
            ->action('Ver ticket', $this->ticketUrlFor($notifiable, $ticket))
            ->line($this->log->isBreach()
                ? 'Revisa el ticket y define los siguientes pasos con el equipo.'
                : 'Atiéndelo antes de que se venza el plazo.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $details = $this->details();

        return FilamentNotification::make()
            ->title($this->title())
            ->body("{$details['Métrica']} · {$details['Consumido']} · Asignado: {$details['Asignado']}")
            ->icon($this->log->isBreach() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')
            ->iconColor($this->log->isBreach() ? 'danger' : 'warning')
            ->actions([
                Action::make('view')
                    ->label('Ver ticket')
                    ->url($this->ticketUrlFor($notifiable, $this->ticket()))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Título del aviso, p. ej. "⚠️ SLA al 90%: ticket TK-2026-00012 — Impresora".
     */
    public function title(): string
    {
        $ticket = $this->ticket();
        $prefix = $this->log->isBreach() ? '⛔ SLA incumplido' : "⚠️ SLA al {$this->log->thresholdPercent()}%";

        return "{$prefix}: ticket {$ticket->number} — {$this->subject()}";
    }

    /**
     * Datos del ticket y de la alerta que se muestran en el aviso.
     *
     * @return array{Métrica: string, Consumido: string, Límite: string, Departamento: string, Prioridad: string, Asignado: string}
     */
    protected function details(): array
    {
        $ticket = $this->ticket();
        $percent = $this->log->consumedPercent();

        return [
            'Métrica' => $this->log->metricLabel(),
            'Consumido' => ($percent !== null ? "{$percent} %" : 'sin dato').' ('.SlaService::formatMinutes((int) $this->log->elapsed_minutes).' hábiles)',
            'Límite' => SlaService::formatMinutes((int) $this->log->sla_minutes).' hábiles',
            'Departamento' => KbSuggestionService::plainText($ticket->department?->name, 120) ?: 'sin departamento',
            'Prioridad' => $ticket->priority?->getLabel() ?? 'sin prioridad',
            'Asignado' => KbSuggestionService::plainText($ticket->assignee?->name, 120) ?: 'sin asignar',
        ];
    }

    protected function subject(): string
    {
        return KbSuggestionService::plainText($this->ticket()->subject, 120);
    }

    protected function ticket(): Ticket
    {
        return $this->log->ticket;
    }
}
