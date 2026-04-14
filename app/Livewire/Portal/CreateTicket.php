<?php

namespace App\Livewire\Portal;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Services\TicketService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
#[Title('Crear ticket')]
class CreateTicket extends Component
{
    use WithFileUploads;

    public string $subject = '';

    public string $description = '';

    public ?int $category_id = null;

    public string $impact = 'medio';

    public string $urgency = 'media';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'impact' => ['required', Rule::enum(TicketImpact::class)],
            'urgency' => ['required', Rule::enum(TicketUrgency::class)],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'subject' => 'asunto',
            'description' => 'descripción',
            'category_id' => 'categoría',
            'impact' => 'impacto',
            'urgency' => 'urgencia',
            'attachments' => 'adjuntos',
        ];
    }

    public function getComputedPriorityProperty(): string
    {
        return TicketPriority::fromMatrix(
            TicketImpact::tryFrom($this->impact) ?? TicketImpact::Medio,
            TicketUrgency::tryFrom($this->urgency) ?? TicketUrgency::Media,
        )->getLabel();
    }

    public function save(): void
    {
        $data = $this->validate();

        $ticket = app(TicketService::class)->create(auth()->user(), $data);

        foreach ($this->attachments as $file) {
            $ticket->addMedia($file->getRealPath())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('attachments');
        }

        Flux::toast(
            text: "Ticket {$ticket->number} creado correctamente.",
            variant: 'success',
        );

        $this->redirectRoute('portal.tickets.show', $ticket, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.create-ticket', [
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
