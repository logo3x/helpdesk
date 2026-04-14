<x-filament-panels::page>
    {{-- SLA Compliance Matrix --}}
    <x-filament::section>
        <x-slot name="heading">Cumplimiento SLA por departamento (últimos 30 días)</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-3 py-2 text-left font-medium">Departamento</th>
                        @foreach ($priorities as $p)
                            <th class="px-3 py-2 text-center font-medium">{{ $p->getLabel() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report as $row)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="px-3 py-2 font-medium">{{ $row['department'] }}</td>
                            @foreach ($row['priorities'] as $p)
                                <td class="px-3 py-2 text-center">
                                    @if ($p['total'] > 0)
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                            {{ $p['compliance'] >= 90 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                               ($p['compliance'] >= 70 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                               'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                            {{ $p['compliance'] }}%
                                        </span>
                                        <div class="mt-0.5 text-[10px] text-zinc-400">{{ $p['total'] }} tickets</div>
                                    @else
                                        <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Recent Escalations --}}
    <x-filament::section>
        <x-slot name="heading">Últimas escalaciones</x-slot>

        @if ($escalations->isEmpty())
            <p class="text-sm text-zinc-400">No hay escalaciones recientes.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-3 py-2 text-left font-medium">Ticket</th>
                            <th class="px-3 py-2 text-left font-medium">Tipo</th>
                            <th class="px-3 py-2 text-center font-medium">SLA (min)</th>
                            <th class="px-3 py-2 text-center font-medium">Transcurrido</th>
                            <th class="px-3 py-2 text-left font-medium">Notificado a</th>
                            <th class="px-3 py-2 text-left font-medium">Cuándo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($escalations as $esc)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-3 py-2">
                                    <span class="font-mono text-xs">{{ $esc->ticket?->number }}</span>
                                    <div class="text-[11px] text-zinc-400">{{ Str::limit($esc->ticket?->subject, 40) }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ str_contains($esc->type, 'breach') ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                           'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                        {{ str_replace('_', ' ', $esc->type) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">{{ $esc->sla_minutes }}</td>
                                <td class="px-3 py-2 text-center font-mono">{{ $esc->elapsed_minutes }}</td>
                                <td class="px-3 py-2">{{ $esc->notifiedUser?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-400">{{ $esc->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
