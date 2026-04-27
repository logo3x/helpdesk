<?php

namespace App\Livewire\Portal;

use App\Models\Department;
use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Centro de ayuda público del portal. Lista los artículos KB con
 * `status = published` (los Borrador y Archivado nunca llegan aquí
 * porque scopePublished filtra por status). Permite buscar por
 * título/cuerpo y filtrar por departamento o categoría.
 */
#[Layout('layouts.portal')]
#[Title('Centro de ayuda')]
class KbIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $department = '';

    #[Url]
    public string $category = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartment(): void
    {
        // Si cambia el departamento, la categoría seleccionada puede
        // quedar inconsistente. Reiniciamos para evitar mostrar 0
        // resultados sin razón aparente para el usuario.
        $this->category = '';
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->department = '';
        $this->category = '';
        $this->resetPage();
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<KbArticle> $articles */
        $articles = KbArticle::query()
            ->published()
            ->when($this->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('body', 'like', "%{$s}%");
            }))
            ->when($this->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($this->category, fn ($q, $c) => $q->where('kb_category_id', $c))
            ->with('department:id,name', 'category:id,name')
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('livewire.portal.kb-index', [
            'articles' => $articles,
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => KbCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
