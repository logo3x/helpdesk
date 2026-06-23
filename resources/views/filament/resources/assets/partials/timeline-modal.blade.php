{{-- Resumen del activo --}}
<div class="grid gap-3 sm:grid-cols-4 mb-6">
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">Custodio</p>
        <p class="text-sm font-medium">{{ $record->user?->name ?? '— Sin asignar —' }}</p>
        @if ($record->user?->position)
            <p class="text-xs text-gray-500">{{ $record->user->position }}</p>
        @endif
    </div>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">Proyecto</p>
        <p class="text-sm font-medium">{{ $record->project?->code ?? '—' }}</p>
        @if ($record->project?->name)
            <p class="text-xs text-gray-500">{{ $record->project->name }}</p>
        @endif
    </div>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">Mantenimiento</p>
        @php($status = $record->maintenance_status)
        @if ($status)
            <span @class([
                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                'bg-green-100 text-green-700' => $status === 'vigente',
                'bg-amber-100 text-amber-700' => $status === 'por vencer',
                'bg-red-100 text-red-700' => $status === 'vencido',
            ])>{{ ucfirst($status) }}</span>
            <p class="mt-1 text-xs text-gray-500">Próx.: {{ $record->next_maintenance_at?->translatedFormat('d/m/Y') ?? '—' }}</p>
        @else
            <p class="text-xs text-gray-500">Sin plan</p>
        @endif
    </div>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">Estado</p>
        <p class="text-sm font-medium uppercase">{{ $record->status ?? '—' }}</p>
        @if ($record->field)
            <p class="text-xs text-gray-500">{{ $record->field }}</p>
        @endif
    </div>
</div>

{{-- Timeline --}}
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
                    <x-filament::icon :icon="$event['icon']" class="h-3.5 w-3.5" />
                </span>

                <div class="flex items-center justify-between gap-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $event['title'] }}</h3>
                    <time class="shrink-0 text-xs text-gray-500">
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
