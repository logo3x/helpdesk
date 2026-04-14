<?php

namespace App\Livewire\Portal;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Notifications\TicketCommentedNotification;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class ViewTicket extends Component
{
    use AuthorizesRequests;

    public Ticket $ticket;

    public string $commentBody = '';

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->requester_id === auth()->id(), 403);

        $this->ticket = $ticket;
    }

    public function addComment(): void
    {
        $this->validate([
            'commentBody' => ['required', 'string', 'min:3', 'max:5000'],
        ], attributes: ['commentBody' => 'comentario']);

        $comment = TicketComment::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => auth()->id(),
            'body' => $this->commentBody,
            'is_private' => false,
        ]);

        // Notify the assigned agent (if any) that the requester commented
        if ($this->ticket->assignee && $this->ticket->assigned_to_id !== auth()->id()) {
            $this->ticket->assignee->notify(new TicketCommentedNotification($this->ticket, $comment));
        }

        $this->commentBody = '';

        Flux::toast(text: 'Comentario agregado.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.portal.view-ticket', [
            'comments' => $this->ticket
                ->publicComments()
                ->with('user:id,name')
                ->oldest()
                ->get(),
        ])->title("Ticket {$this->ticket->number}");
    }
}
