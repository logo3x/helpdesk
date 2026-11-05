@props([
    /** Adjuntos (Media de medialibrary) de un ticket o comentario. */
    'files' => [],
    /** Tarjetas más pequeñas, para mostrarlas dentro de una burbuja de la conversación. */
    'compact' => false,
])

@php
    $items = \App\Services\TicketAttachmentService::viewerItems($files);
@endphp

@if (count($items))
    {{--
        Visor de adjuntos reutilizable (paneles Filament y portal). Solo usa
        Alpine y Tailwind: no depende de Flux ni de Filament. Los archivos se
        sirven por la ruta autenticada tickets.attachments.show; nunca por
        /storage.
    --}}
    <div
        {{ $attributes->class(['attachment-viewer']) }}
        x-data="{
            items: @js($items),
            index: null,
            get current() { return this.index === null ? null : this.items[this.index]; },
            open(position) { this.index = position; document.documentElement.classList.add('overflow-hidden'); },
            close() { this.index = null; document.documentElement.classList.remove('overflow-hidden'); },
            previous() { if (this.items.length > 1) { this.index = (this.index + this.items.length - 1) % this.items.length; } },
            next() { if (this.items.length > 1) { this.index = (this.index + 1) % this.items.length; } },
        }"
        {{-- En captura: si el visor está dentro de un modal de Filament, Esc
             cierra solo el visor y no el modal de atrás. --}}
        x-on:keydown.escape.window.capture="if (index !== null) { $event.stopPropagation(); close(); }"
        x-on:keydown.arrow-left.window="if (index !== null) { previous(); }"
        x-on:keydown.arrow-right.window="if (index !== null) { next(); }"
    >
        <div @class([
            'grid gap-2',
            'grid-cols-[repeat(auto-fill,minmax(11rem,1fr))]' => $compact,
            'grid-cols-[repeat(auto-fill,minmax(13.5rem,1fr))] gap-3' => ! $compact,
        ])>
            @foreach ($items as $item)
                <button
                    type="button"
                    x-on:click="open({{ $loop->index }})"
                    title="{{ $item['name'] }}"
                    @class([
                        'group flex min-w-0 items-center gap-3 rounded-xl border border-zinc-200 bg-white text-left transition hover:-translate-y-0.5 hover:border-sky-400 hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 dark:border-white/10 dark:bg-white/5 dark:hover:border-sky-500',
                        'px-2.5 py-2' => $compact,
                        'px-3 py-2.5' => ! $compact,
                    ])
                >
                    @if ($item['kind'] === 'image')
                        <img src="{{ $item['url'] }}" alt="" loading="lazy"
                             @class(['shrink-0 rounded-lg object-cover', 'size-9' => $compact, 'size-11' => ! $compact])>
                    @else
                        <span @class(['shrink-0 leading-none', 'text-2xl' => $compact, 'text-3xl' => ! $compact]) aria-hidden="true">{{ $item['icon'] }}</span>
                    @endif

                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-[0.8125rem] font-semibold text-zinc-800 dark:text-zinc-100">{{ $item['name'] }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $item['size'] }} · <span class="text-sky-600 group-hover:underline dark:text-sky-400">Ver</span>
                        </span>
                    </span>
                </button>
            @endforeach
        </div>

        <template x-teleport="body">
            <div
                x-show="index !== null"
                x-transition.opacity.duration.150ms
                style="display: none"
                class="fixed inset-0 z-[100] flex flex-col bg-zinc-950/80 p-2 backdrop-blur-sm sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="current ? current.name : ''"
                x-on:click.self="close()"
            >
                <div class="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                    {{-- Cabecera: nombre, posición y acciones --}}
                    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-4 py-3 dark:border-white/10">
                        <span class="text-xl leading-none" aria-hidden="true" x-text="current?.icon"></span>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100" x-text="current?.name"></span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                <span x-text="current?.size"></span>
                                <template x-if="items.length > 1">
                                    <span x-text="' · ' + (index + 1) + ' de ' + items.length"></span>
                                </template>
                            </span>
                        </div>

                        <a :href="current?.url" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/10">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            <span class="hidden sm:inline">Abrir en pestaña nueva</span>
                        </a>
                        <a :href="current?.download"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-sky-700">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Descargar
                        </a>
                        <button type="button" x-on:click="close()" title="Cerrar (Esc)"
                                class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                            <span class="sr-only">Cerrar</span>
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    {{-- Contenido según el tipo de archivo --}}
                    <div class="relative flex min-h-0 flex-1 items-center justify-center bg-zinc-100 dark:bg-zinc-950">
                        <template x-if="current && current.kind === 'image'">
                            <img :src="current.url" :alt="current.name" class="max-h-full max-w-full object-contain p-2">
                        </template>

                        <template x-if="current && current.kind === 'pdf'">
                            <iframe :src="current.url" :title="current.name" class="size-full min-h-[70vh] border-0 bg-white"></iframe>
                        </template>

                        <template x-if="current && current.kind === 'text'">
                            <iframe :src="current.url" :title="current.name" sandbox class="size-full min-h-[70vh] border-0 bg-white"></iframe>
                        </template>

                        <template x-if="current && current.kind === 'other'">
                            <div class="flex flex-col items-center gap-3 p-8 text-center">
                                <span class="text-6xl leading-none" aria-hidden="true" x-text="current.icon"></span>
                                <p class="max-w-sm text-sm text-zinc-600 dark:text-zinc-300">
                                    Este tipo de archivo no tiene vista previa. Descárgalo para abrirlo en tu equipo.
                                </p>
                                <a :href="current.download"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                    Descargar
                                </a>
                            </div>
                        </template>

                        {{-- Navegación entre adjuntos (también con las flechas del teclado) --}}
                        <template x-if="items.length > 1">
                            <div>
                                <button type="button" x-on:click="previous()" title="Anterior (←)"
                                        class="absolute left-2 top-1/2 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-zinc-700 shadow hover:bg-white dark:bg-zinc-800/90 dark:text-zinc-100 dark:hover:bg-zinc-800">
                                    <span class="sr-only">Anterior</span>
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                                </button>
                                <button type="button" x-on:click="next()" title="Siguiente (→)"
                                        class="absolute right-2 top-1/2 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-zinc-700 shadow hover:bg-white dark:bg-zinc-800/90 dark:text-zinc-100 dark:hover:bg-zinc-800">
                                    <span class="sr-only">Siguiente</span>
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
