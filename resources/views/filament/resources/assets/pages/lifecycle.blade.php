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
            <ol class="relative ml-3 border-s border-gray-200 dark:border-gray-700">
                @foreach ($events as $event)
                    <li class="mb-6 ms-6">
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
        @endif
    </x-filament::section>
</x-filament-panels::page>
