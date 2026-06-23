<div x-data="{}"
     x-init="
        document.querySelectorAll('.kb-card').forEach((el, i) => {
            el.style.animationDelay = (i * 40) + 'ms';
        });
     ">

    <style>
        @keyframes kbCardIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .kb-card {
            animation: kbCardIn .3s ease both;
            opacity: 0;
        }
    </style>

    {{-- Header --}}
    <div class="mb-6">
        <flux:heading size="xl">Centro de ayuda</flux:heading>
        <flux:text class="mt-0.5 text-zinc-400">Busca artículos publicados por los equipos de soporte. Si no encuentras lo que necesitas, abre un ticket o usa el asistente.</flux:text>
    </div>

    {{-- Filtros --}}
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Buscar por título o contenido..." />
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
        <div class="mb-4">
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">
                Limpiar filtros
            </flux:button>
        </div>
    @endif

    {{-- Grid de artículos --}}
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            <a href="{{ route('portal.kb.show', $article->slug) }}" wire:navigate
               class="kb-card group flex h-full flex-col overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80 dark:hover:border-zinc-600">

                <div class="mb-2.5 flex flex-wrap items-center gap-1.5">
                    @if ($article->department)
                        <flux:badge size="sm" color="zinc">{{ $article->department->name }}</flux:badge>
                    @endif
                    @if ($article->category)
                        <flux:badge size="sm" color="sky">{{ $article->category->name }}</flux:badge>
                    @endif
                </div>

                <div class="mb-1 flex items-start gap-2">
                    <flux:icon name="document-text" class="mt-0.5 size-4 shrink-0 text-emerald-400" />
                    <div class="min-w-0 flex-1 text-sm font-semibold leading-snug text-zinc-900 line-clamp-2 transition-colors group-hover:text-emerald-600 dark:text-zinc-100 dark:group-hover:text-emerald-400">
                        {{ $article->title }}
                    </div>
                </div>

                <flux:text size="sm" class="mt-1 line-clamp-3 text-zinc-400">
                    {{ \Illuminate\Support\Str::limit(strip_tags($article->body), 150) }}
                </flux:text>

                <div class="mt-auto flex items-center gap-3 pt-3 text-xs text-zinc-400">
                    <span class="flex items-center gap-1">
                        <flux:icon name="eye" class="size-3" />
                        {{ $article->views_count }} vistas
                    </span>
                    @if ($article->helpful_count > 0)
                        <span class="flex items-center gap-1">
                            <flux:icon name="hand-thumb-up" class="size-3 text-emerald-400" />
                            {{ $article->helpful_count }}
                        </span>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-zinc-300 py-14 text-center dark:border-zinc-600">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="document-magnifying-glass" class="size-6 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="text-zinc-500">Sin resultados</flux:heading>
                <flux:text size="sm" class="mt-1 text-zinc-400">
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
