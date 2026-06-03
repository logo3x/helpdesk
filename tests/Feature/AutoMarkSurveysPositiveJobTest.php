<?php

use App\Jobs\AutoMarkSurveysPositiveJob;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;

it('marca con 5★ las encuestas sin respuesta tras la ventana', function () {
    config()->set('tickets.csat_auto_positive_days', 7);

    $this->travel(-8)->days();
    $ticket = Ticket::factory()->resolved()->create();
    $survey = SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
    ]);
    $this->travelBack();

    (new AutoMarkSurveysPositiveJob)->handle();

    $survey->refresh();
    expect($survey->rating)->toBe(5)
        ->and($survey->responded_at)->not->toBeNull()
        ->and($survey->comment)->toContain('auto-positiva');
});

it('no toca encuestas dentro de la ventana', function () {
    config()->set('tickets.csat_auto_positive_days', 7);

    $this->travel(-3)->days();
    $ticket = Ticket::factory()->resolved()->create();
    $survey = SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
    ]);
    $this->travelBack();

    (new AutoMarkSurveysPositiveJob)->handle();

    $survey->refresh();
    expect($survey->rating)->toBeNull()
        ->and($survey->responded_at)->toBeNull();
});

it('no sobrescribe encuestas que el usuario ya respondió', function () {
    config()->set('tickets.csat_auto_positive_days', 7);

    $this->travel(-10)->days();
    $ticket = Ticket::factory()->resolved()->create();
    $survey = SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
        'rating' => 3,
        'comment' => 'Regular',
        'responded_at' => now()->addDays(8),
    ]);
    $this->travelBack();

    (new AutoMarkSurveysPositiveJob)->handle();

    $survey->refresh();
    expect($survey->rating)->toBe(3)
        ->and($survey->comment)->toBe('Regular');
});

it('respeta el umbral configurado vía config', function () {
    config()->set('tickets.csat_auto_positive_days', 3);

    $this->travel(-4)->days();
    $ticket = Ticket::factory()->resolved()->create();
    $survey = SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
    ]);
    $this->travelBack();

    (new AutoMarkSurveysPositiveJob)->handle();

    expect($survey->fresh()->rating)->toBe(5);
});
