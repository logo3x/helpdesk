<?php

namespace App\Livewire\Portal;

use App\Models\Asset;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function render(): View
    {
        /** @var LengthAwarePaginator<Asset> $assets */
        $assets = Asset::query()
            ->where('user_id', auth()->id())
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('asset_tag', 'like', "%{$s}%")
                ->orWhere('hostname', 'like', "%{$s}%")
                ->orWhere('serial_number', 'like', "%{$s}%")
                ->orWhere('manufacturer', 'like', "%{$s}%")
                ->orWhere('model', 'like', "%{$s}%")
            ))
            ->with('project:id,code,name')
            ->latest()
            ->paginate(10);

        return view('livewire.portal.my-assets', [
            'assets' => $assets,
        ]);
    }
}
