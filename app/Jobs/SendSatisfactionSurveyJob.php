<?php

namespace App\Jobs;

use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Notifications\SatisfactionSurveyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched when a ticket is closed. Creates a survey record and sends
 * a notification to the requester with a one-time rating link.
 */
class SendSatisfactionSurveyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
    ) {}

    public function handle(): void
    {
        // Don't send duplicate surveys
        if (SatisfactionSurvey::where('ticket_id', $this->ticket->id)->exists()) {
            return;
        }

        $survey = SatisfactionSurvey::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->ticket->requester_id,
        ]);

        $this->ticket->requester->notify(
            new SatisfactionSurveyNotification($this->ticket, $survey),
        );
    }
}
