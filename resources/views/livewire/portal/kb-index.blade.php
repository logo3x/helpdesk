<div>
    <div class="mb-6">
        <flux:heading size="xl">Centro de ayuda</flux:heading>
        <flux:text class="mt-1">Busca artículos publicados por los equipos de soporte. Si no encuentras lo que necesitas, abre un ticket o usa el asistente.</flux:text>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Buscar por título o contenido..."
        />

        <flux:select wire:model.live="department" placeholder="Todos los departamentos">
            <flux:select.option value="">Todos los departamentos</flux:select.option>
            @foreach ($departments as $dept)
                <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="category" placeholder="Todas las categorías">
            <flux:select.option value="">Todas las categorías</flux:select.option>
            @foreach ($categories as $cat)
                <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($search || $department || $category)
        <div class="mb-3">
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">
                Limpiar filtros
            </flux:button>
        </div>
    @endif

    {{-- Lista --}}
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            <a
                href="{{ route('portal.kb.show', $article->slug) }}"
                wire:navigate
                class="flex h-full flex-col rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 hover:shadow-sm dark:border-zinc-700 dark:hover:border-zinc-500"
            >
                <div class="mb-2 flex flex-wrap items-center gap-1.5">
                    @if ($article->department)
                        <flux:badge size="sm" color="zinc">{{ $article->department->name }}</flux:badge>
                    @endif
                    @if ($article->category)
                        <flux:badge size="sm" color="sky">{{ $article->category->name }}</flux:badge>
                    @endif
                </div>

                <flux:heading size="sm" class="mb-1 line-clamp-2">{{ $article->title }}</flux:heading>

                <flux:text size="sm" class="line-clamp-3 text-zinc-500">
                    {{ \Illuminate\Support\Str::limit(strip_tags($article->body), 160) }}
                </flux:text>

                <div class="mt-auto pt-3">
                    <flux:text size="xs" class="text-zinc-400">
                        <flux:icon name="eye" variant="micro" class="inline" />
                        {{ $article->views_count }} vistas
                        @if ($article->helpful_count > 0)
                            · 👍 {{ $article->helpful_count }}
                        @endif
                    </flux:text>
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-lg border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-600">
                <flux:icon name="document-magnifying-glass" class="mx-auto mb-3 size-10 text-zinc-400" />
                <flux:heading size="sm">Sin resultados</flux:heading>
                <flux:text class="mt-1">
                    @if ($search || $department || $category)
                        No hay artículos que coincidan con los filtros aplicados.
                    @else
                        Aún no hay artículos publicados.
                    @endif
                </flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $articles->links() }}
    </div>
</div>
