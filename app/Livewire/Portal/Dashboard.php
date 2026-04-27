<?php

namespace App\Livewire\Portal;

use App\Enums\TicketStatus;
use App\Models\KbArticle;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard de bienvenida del portal del solicitante.
 *
 * Sustituye el redirect viejo a "Mis tickets" por una pantalla con
 * saludo + stats personales + accesos rápidos + últimos tickets,
 * para que el usuario tenga una vista amigable al entrar en lugar
 * de aterrizar en una lista pelada.
 */
#[Layout('layouts.portal')]
#[Title('Inicio')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        $myTicketsBase = Ticket::query()->where('requester_id', $user?->id);

        $openCount = (clone $myTicketsBase)->open()->count();
        $waitingMyResponse = (clone $myTicketsBase)
            ->where('status', TicketStatus::PendienteCliente)
            ->count();
        $resolvedCount = (clone $myTicketsBase)
            ->whereIn('status', [TicketStatus::Resuelto, TicketStatus::Cerrado])
            ->count();
        $totalCount = (clone $myTicketsBase)->count();

        $recent = (clone $myTicketsBase)
            ->with('category:id,name', 'assignee:id,name')
            ->latest()
            ->limit(5)
            ->get();

        $featuredKb = KbArticle::query()
            ->published()
            ->with('category:id,name')
            ->orderByDesc('views_count')
            ->limit(4)
            ->get(['id', 'slug', 'title', 'kb_category_id', 'views_count']);

        return view('livewire.portal.dashboard', [
            'user' => $user,
            'openCount' => $openCount,
            'waitingMyResponse' => $waitingMyResponse,
            'resolvedCount' => $resolvedCount,
            'totalCount' => $totalCount,
            'recent' => $recent,
            'featuredKb' => $featuredKb,
        ]);
    }
}
