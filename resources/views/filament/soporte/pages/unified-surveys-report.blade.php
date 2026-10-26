<x-filament-panels::page>
    {{-- Filtros --}}
    <x-filament::section>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div>
                <label for="source" class="mb-1 block text-sm font-medium">Origen</label>
                <select
                    id="source"
                    wire:model.live="source"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                >
                    @foreach ($sourceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="departmentId" class="mb-1 block text-sm font-medium">Departamento</label>
                <select
                    id="departmentId"
                    wire:model.live="departmentId"
                    @disabled(! $canChooseDepartment)
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                >
                    @if ($canChooseDepartment)
                        <option value="">Todos los departamentos</option>
                    @endif
                    @foreach ($departmentOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="window" class="mb-1 block text-sm font-medium">Ventana</label>
                <select
                    id="window"
                    wire:model.live="window"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                >
                    @foreach ($windowOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="minRating" class="mb-1 block text-sm font-medium">Calificación mínima</label>
                <select
                    id="minRating"
                    wire:model.live="minRating"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                >
                    <option value="">Cualquiera</option>
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}">{{ $i }}★ o más</option>
                    @endfor
                </select>
            </div>
        </div>
    </x-filament::section>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <x-filament::section>
            <div class="text-xs uppercase text-gray-500">Total</div>
            <div class="text-2xl font-bold">{{ $stats['total'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs uppercase text-gray-500">Respondidas</div>
            <div class="text-2xl font-bold text-emerald-600">{{ $stats['answered'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs uppercase text-gray-500">Pendientes</div>
            <div class="text-2xl font-bold text-amber-600">{{ $stats['pending'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs uppercase text-gray-500">Calificación media</div>
            <div class="text-2xl font-bold">
                @if ($stats['avg'] !== null)
                    {{ $stats['avg'] }}★
                @else
                    —
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs uppercase text-gray-500">Por origen</div>
            <div class="text-sm">
                <span class="font-semibold text-sky-600">{{ $stats['byTicket'] }}</span> ticket
                ·
                <span class="font-semibold text-amber-600">{{ $stats['byMaintenance'] }}</span> mto.
            </div>
        </x-filament::section>
    </div>

    {{-- Tabla unificada --}}
    <x-filament::section>
        <x-slot name="heading">Encuestas</x-slot>
        <x-slot name="description">
            Combina encuestas de tickets y de mantenimientos. El badge indica el origen.
        </x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500">No hay encuestas en la ventana seleccionada con esos filtros.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2 pr-4 text-left font-semibold">Origen</th>
                            <th class="py-2 pr-4 text-left font-semibold">Relacionado con</th>
                            <th class="py-2 pr-4 text-left font-semibold">Departamento</th>
                            <th class="py-2 pr-4 text-left font-semibold">Usuario</th>
                            <th class="py-2 pr-4 text-right font-semibold">Calificación</th>
                            <th class="py-2 pr-4 text-left font-semibold">Comentario</th>
                            <th class="py-2 pr-4 text-left font-semibold">Respondida</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="py-2 pr-4">
                                    @if ($row['source'] === 'ticket')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-900/40 dark:text-sky-200">
                                            🎫 Ticket
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                            🔧 Mantenimiento
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($row['related_url'])
                                        <a href="{{ $row['related_url'] }}" class="text-primary-600 hover:underline" wire:navigate>
                                            {{ $row['related_label'] }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">{{ $row['related_label'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $row['department'] ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $row['user_name'] ?? '—' }}</td>
                                <td class="py-2 pr-4 text-right">
                                    @if ($row['rating'])
                                        <span class="font-mono">{{ str_repeat('★', $row['rating']).str_repeat('☆', 5 - $row['rating']) }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">pendiente</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($row['comment'])
                                        <span class="line-clamp-2 text-gray-700 dark:text-gray-300">{{ $row['comment'] }}</span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                    {{ $row['responded_at']?->translatedFormat('d M Y H:i') ?? 'Pendiente' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
