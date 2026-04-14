<?php

namespace App\Livewire\Portal;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Mis tickets')]
class MyTickets extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<Ticket> $tickets */
        $tickets = Ticket::query()
            ->where('requester_id', auth()->id())
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$s}%")
                ->orWhere('subject', 'like', "%{$s}%")
            ))
            ->when($this->status, fn ($q, $s) => $q->where('status', $s))
            ->with('category:id,name', 'assignee:id,name')
            ->latest()
            ->paginate(10);

        return view('livewire.portal.my-tickets', [
            'tickets' => $tickets,
            'statusOptions' => TicketStatus::cases(),
        ]);
    }
}
