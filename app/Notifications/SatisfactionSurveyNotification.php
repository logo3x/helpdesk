<?php

namespace App\Notifications;

use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SatisfactionSurveyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public SatisfactionSurvey $survey,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $surveyUrl = url("/portal/survey/{$this->survey->token}");

        return (new MailMessage)
            ->subject("[{$this->ticket->number}] ¿Cómo fue tu experiencia?")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu ticket **{$this->ticket->number}** — {$this->ticket->subject} ha sido cerrado.")
            ->line('Nos gustaría saber qué tan satisfecho quedaste con la atención recibida.')
            ->action('Calificar atención', $surveyUrl)
            ->line('Tu opinión nos ayuda a mejorar. ¡Gracias!');
    }
}
