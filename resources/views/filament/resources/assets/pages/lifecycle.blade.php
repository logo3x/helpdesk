<x-filament-panels::page>
    {{-- ── Resumen del activo ────────────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section compact>
            <x-slot name="heading">Custodio actual</x-slot>
            <div class="text-sm">
                {{ $this->record->user?->name ?? '— Sin asignar —' }}
                @if ($this->record->user?->position)
                    <div class="text-xs text-gray-500">{{ $this->record->user->position }}</div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Proyecto</x-slot>
            <div class="text-sm">
                {{ $this->record->project?->code ?? '—' }}
                @if ($this->record->project?->name)
                    <div class="text-xs text-gray-500">{{ $this->record->project->name }}</div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Mantenimiento</x-slot>
            <div class="text-sm">
                @php($status = $this->record->maintenance_status)
                @if ($status)
                    <span @class([
                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-green-100 text-green-700' => $status === 'vigente',
                        'bg-amber-100 text-amber-700' => $status === 'por vencer',
                        'bg-red-100 text-red-700' => $status === 'vencido',
                    ])>
                        {{ ucfirst($status) }}
                    </span>
                    <div class="mt-1 text-xs text-gray-500">
                        Próx.: {{ $this->record->next_maintenance_at?->translatedFormat('d/m/Y') ?? '—' }}
                    </div>
                @else
                    <span class="text-xs text-gray-500">Sin plan de mantenimiento</span>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Estado</x-slot>
            <div class="text-sm font-medium uppercase">{{ $this->record->status ?? '—' }}</div>
            @if ($this->record->field)
                <div class="text-xs text-gray-500">{{ $this->record->field }}</div>
            @endif
        </x-filament::section>
    </div>

    {{-- ── Timeline unificado ─────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Línea de tiempo del activo</x-slot>
        <x-slot name="description">
            Eventos consolidados: creación, scans automáticos, actas de entrega y
            cambios manuales. Los scans del agente se muestran capados a los últimos 50.
        </x-slot>

        @php($events = $this->getTimeline())

        @if (empty($events))
            <p class="text-sm text-gray-500">Este activo aún no tiene eventos registrados.</p>
        @else
            {{-- Filtros + imprimir --}}
            <div
                x-data="{
                    filters: { created: true, handover: true, history: true, scan: true },
                    get visible() {
                        return Object.entries(this.filters)
                            .filter(([,v]) => v)
                            .map(([k]) => k);
                    },
                    print() {
                        window.print();
                    }
                }"
                class="mb-4"
            >
                {{-- Fila de checkboxes + botón imprimir --}}
                <div class="flex flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50 print:hidden">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 shrink-0">Mostrar:</span>

                    <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="filters.created" class="rounded border-gray-300 text-primary-600">
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-full bg-primary-500"></span>
                            Creación
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="filters.handover" class="rounded border-gray-300 text-amber-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                            Actas de entrega
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="filters.history" class="rounded border-gray-300 text-gray-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-full bg-gray-400"></span>
                            Cambios manuales
                        </span>
                    </label>

                    <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="filters.scan" class="rounded border-gray-300 text-sky-500">
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-full bg-sky-500"></span>
                            Scans
                        </span>
                    </label>

                    <div class="ml-auto">
                        <button
                            type="button"
                            @click="print()"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            <x-filament::icon icon="heroicon-o-printer" class="h-4 w-4" />
                            Imprimir
                        </button>
                    </div>
                </div>

                {{-- Timeline --}}
                <ol class="relative ml-3 mt-4 border-s border-gray-200 dark:border-gray-700">
                    @foreach ($events as $event)
                        <li
                            class="mb-6 ms-6"
                            x-show="visible.includes('{{ $event['type'] }}')"
                            x-cloak
                        >
                            <span @class([
                                'absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900',
                                'bg-primary-100 text-primary-700' => $event['color'] === 'primary',
                                'bg-amber-100 text-amber-700' => $event['color'] === 'warning',
                                'bg-sky-100 text-sky-700' => $event['color'] === 'info',
                                'bg-gray-100 text-gray-700' => $event['color'] === 'gray',
                            ])>
                                <x-filament::icon
                                    :icon="$event['icon']"
                                    class="h-3.5 w-3.5"
                                />
                            </span>

                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $event['title'] }}
                                </h3>
                                <time class="shrink-0 text-xs text-gray-500" title="{{ $event['date']->toIso8601String() }}">
                                    {{ $event['date']->translatedFormat('d M Y · H:i') }}
                                </time>
                            </div>

                            @if ($event['description'])
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event['description'] }}</p>
                            @endif

                            @if (! empty($event['meta']))
                                <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                                    @foreach ($event['meta'] as $label => $value)
                                        @if (! blank($value))
                                            <div class="flex gap-2">
                                                <dt class="text-gray-500">{{ $label }}:</dt>
                                                <dd class="text-gray-800 dark:text-gray-200">{{ $value }}</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
