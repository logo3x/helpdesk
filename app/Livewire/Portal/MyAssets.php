<?php

namespace App\Livewire\Portal;

use App\Models\Asset;
use App\Models\AssetHandover;
use App\Models\MaintenanceSurvey;
use App\Models\User;
use App\Notifications\AssetHandoverConfirmedNotification;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification as MailNotification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Mis activos')]
class MyAssets extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Marca el handover como recibido por el custodio. Solo el propio
     * receptor puede confirmarlo; cualquier intento ajeno es ignorado
     * silenciosamente (no exponer existencia de handovers de terceros).
     */
    public function confirmHandover(int $handoverId): void
    {
        $handover = AssetHandover::query()
            ->where('id', $handoverId)
            ->where('received_by_user_id', auth()->id())
            ->whereNull('received_confirmed_at')
            ->first();

        if (! $handover) {
            return;
        }

        $handover->forceFill(['received_confirmed_at' => now()])->save();
        $handover->load('asset');

        // Notificar por email al equipo de soporte (admins y supervisores)
        $recipients = User::role(['super_admin', 'admin', 'supervisor_soporte'])->get();
        MailNotification::send($recipients, new AssetHandoverConfirmedNotification($handover));

        Notification::make()
            ->title('Recepción confirmada')
            ->body("Acta #{$handover->acta_number} marcada como recibida. Se notificó al equipo de soporte.")
            ->success()
            ->send();
    }

    public function acceptAsset(int $assetId): void
    {
        $asset = Asset::where('id', $assetId)
            ->where('user_id', auth()->id())
            ->whereNull('accepted_at')
            ->first();

        if (! $asset) {
            return;
        }

        $asset->forceFill([
            'accepted_at' => now(),
            'accepted_by_user_id' => auth()->id(),
        ])->save();

        Notification::make()
            ->title('Activo confirmado')
            ->body('Has confirmado la recepción del activo.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        $userId = auth()->id();

        /** @var LengthAwarePaginator<Asset> $assets */
        $assets = Asset::query()
            ->where('user_id', $userId)
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('asset_tag', 'like', "%{$s}%")
                ->orWhere('hostname', 'like', "%{$s}%")
                ->orWhere('serial_number', 'like', "%{$s}%")
                ->orWhere('manufacturer', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")
            ))
            ->with([
                'project:id,code,name',
                'acceptedBy:id,name',
                'handovers' => fn ($q) => $q->where('received_by_user_id', $userId)
                    ->whereNull('received_confirmed_at')
                    ->latest('delivered_at'),
            ])
            ->latest()
            ->paginate(10);

        // Encuestas de mantenimiento pendientes del usuario
        $pendingSurveys = MaintenanceSurvey::query()
            ->where('user_id', $userId)
            ->whereNull('responded_at')
            ->with('asset:id,hostname,asset_tag,manufacturer,model')
            ->get();

        return view('livewire.portal.my-assets', [
            'assets' => $assets,
            'pendingSurveys' => $pendingSurveys,
        ]);
    }
}
