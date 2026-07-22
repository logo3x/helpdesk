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

    public int $rating = 0;

    public string $comment = '';

    public function mount(string $token): void
    {
        $this->survey = SatisfactionSurvey::with('ticket')->where('token', $token)->firstOrFail();

        abort_if(auth()->id() !== $this->survey->user_id, 403);
    }

    public function submit(): void
    {
        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if (! $this->survey->isPending()) {
            Notification::make()->title('Ya respondiste esta encuesta.')->warning()->send();

            return;
        }

        $this->survey->forceFill([
            'rating' => $this->rating,
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
