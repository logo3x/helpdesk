<x-filament-widgets::widget>
    @php
        $data = $this->getViewData();
    @endphp

    <div class="overflow-hidden rounded-2xl border border-gray-200/60 bg-gradient-to-br from-slate-800 via-slate-700 to-slate-600 shadow-md dark:border-slate-700">
        <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">

            {{-- Saludo principal --}}
            <div class="flex items-center gap-4">
                {{-- Avatar con iniciales --}}
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/10 text-lg font-bold text-white ring-2 ring-white/20">
                    {{ mb_strtoupper(mb_substr($data['firstName'], 0, 1)) }}
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-widest text-slate-400">
                        {{ $data['greeting'] }}
                    </p>
                    <h2 class="text-xl font-bold leading-tight text-white">
                        {{ $data['firstName'] }}
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ $data['roleLabel'] }}
                        @if ($data['fullName'] !== $data['firstName'])
                            · {{ $data['fullName'] }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Indicador de tickets abiertos asignados --}}
            <div class="flex items-center gap-3">
                <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-center">
                    <p class="text-2xl font-bold text-white">{{ $data['myOpen'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ $data['myOpen'] === 1 ? 'ticket abierto' : 'tickets abiertos' }} a tu cargo
                    </p>
                </div>

                {{-- Acceso rápido --}}
                <a href="{{ route('filament.soporte.resources.tickets.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/20">
                    <x-filament::icon icon="heroicon-o-ticket" class="h-4 w-4 opacity-80" />
                    Ver tickets
                </a>
            </div>
        </div>

        {{-- Barra de fecha --}}
        <div class="border-t border-white/10 px-6 py-2 text-xs text-slate-500">
            {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
        </div>
    </div>
</x-filament-widgets::widget>
