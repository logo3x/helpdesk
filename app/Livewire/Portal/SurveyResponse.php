<?php

namespace App\Livewire\Portal;

use App\Models\SatisfactionSurvey;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Encuesta de satisfacción')]
class SurveyResponse extends Component
{
    public SatisfactionSurvey $survey;

    // Dimension ratings (1-5 each)
    public int $rating_attention = 0;

    public int $rating_contact = 0;

    public int $rating_resolution = 0;

    public int $rating_time = 0;

    public int $rating_knowledge = 0;

    public int $rating_attitude = 0;

    public string $comment = '';

    public function mount(string $token): void
    {
        $this->survey = SatisfactionSurvey::with('ticket')->where('token', $token)->firstOrFail();

        abort_if(auth()->id() !== $this->survey->user_id, 403);
    }

    public function submit(): void
    {
        $this->validate([
            'rating_attention' => 'required|integer|min:1|max:5',
            'rating_contact' => 'required|integer|min:1|max:5',
            'rating_resolution' => 'required|integer|min:1|max:5',
            'rating_time' => 'required|integer|min:1|max:5',
            'rating_knowledge' => 'required|integer|min:1|max:5',
            'rating_attitude' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ], [
            'rating_attention.required' => 'Califica Atención general.',
            'rating_contact.required' => 'Califica Facilidad de contacto.',
            'rating_resolution.required' => 'Califica Resolución de tu incidente.',
            'rating_time.required' => 'Califica Tiempo de solución.',
            'rating_knowledge.required' => 'Califica Conocimiento técnico.',
            'rating_attitude.required' => 'Califica Amabilidad y disposición.',
            'rating_attention.min' => 'Selecciona una calificación para Atención general.',
            'rating_contact.min' => 'Selecciona una calificación para Facilidad de contacto.',
            'rating_resolution.min' => 'Selecciona una calificación para Resolución de tu incidente.',
            'rating_time.min' => 'Selecciona una calificación para Tiempo de solución.',
            'rating_knowledge.min' => 'Selecciona una calificación para Conocimiento técnico.',
            'rating_attitude.min' => 'Selecciona una calificación para Amabilidad y disposición.',
        ]);

        if (! $this->survey->isPending()) {
            Notification::make()->title('Ya respondiste esta encuesta.')->warning()->send();

            return;
        }

        $avg = round(
            ($this->rating_attention + $this->rating_contact + $this->rating_resolution
                + $this->rating_time + $this->rating_knowledge + $this->rating_attitude) / 6,
        );

        $this->survey->forceFill([
            'rating' => $avg,
            'rating_attention' => $this->rating_attention,
            'rating_contact' => $this->rating_contact,
            'rating_resolution' => $this->rating_resolution,
            'rating_time' => $this->rating_time,
            'rating_knowledge' => $this->rating_knowledge,
            'rating_attitude' => $this->rating_attitude,
            'comment' => $this->comment ?: null,
            'responded_at' => now(),
        ])->save();

        Notification::make()
            ->title('¡Gracias por tu calificación!')
            ->body('Tu opinión nos ayuda a mejorar el servicio.')
            ->success()
            ->send();

        $this->redirect(route('portal.tickets.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.survey-response', [
            'survey' => $this->survey,
        ]);
    }
}
