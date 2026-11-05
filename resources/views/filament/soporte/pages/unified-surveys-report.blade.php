{{--
    Informe unificado de encuestas (tickets + mantenimientos).
    Usa componentes de Filament (selects, badges, secciones) y estilos en
    línea para la distribución: los paneles no incluyen utilidades de
    Tailwind, así que clases como grid-cols-4 o text-emerald-600 no aplican.
--}}
@php
    $grid = fn (string $min) => "display: grid; grid-template-columns: repeat(auto-fit, minmax({$min}, 1fr)); gap: 1rem;";
    $label = 'display: block; margin-bottom: 0.375rem; font-size: 0.8125rem; font-weight: 600;';
    $kpiLabel = 'font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em; opacity: .65;';
    $kpiValue = 'font-size: 1.75rem; font-weight: 700; line-height: 1.2; margin-top: 0.25rem;';
    $th = 'padding: 0.625rem 0.75rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em; opacity: .7; white-space: nowrap;';
    $td = 'padding: 0.75rem; vertical-align: top;';
    $responseRate = $stats['total'] > 0 ? round($stats['answered'] / $stats['total'] * 100) : 0;
@endphp

<x-filament-panels::page>
    {{-- Filtros --}}
    <x-filament::section icon="heroicon-o-funnel" heading="Filtros" compact>
        <div style="{{ $grid('200px') }}">
            <div>
                <label for="source" style="{{ $label }}">Origen</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="source" wire:model.live="source">
                        @foreach ($sourceOptions as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label for="departmentId" style="{{ $label }}">Departamento</label>
                <x-filament::input.wrapper :disabled="! $canChooseDepartment">
                    <x-filament::input.select id="departmentId" wire:model.live="departmentId" :disabled="! $canChooseDepartment">
                        @if ($canChooseDepartment)
                            <option value="">Todos los departamentos</option>
                        @endif
                        @foreach ($departmentOptions as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label for="window" style="{{ $label }}">Periodo</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="window" wire:model.live="window">
                        @foreach ($windowOptions as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div>
                <label for="minRating" style="{{ $label }}">Calificación mínima</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="minRating" wire:model.live="minRating">
                        <option value="">Cualquiera</option>
                        @for ($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }}★ o más</option>
                        @endfor
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    {{-- Indicadores --}}
    <div style="{{ $grid('170px') }}">
        <x-filament::section compact>
            <div style="{{ $kpiLabel }}">📋 Encuestas</div>
            <div style="{{ $kpiValue }}">{{ $stats['total'] }}</div>
            <div style="font-size: 0.75rem; opacity: .7;">🎫 {{ $stats['byTicket'] }} tickets · 🔧 {{ $stats['byMaintenance'] }} mantenimientos</div>
        </x-filament::section>

        <x-filament::section compact>
            <div style="{{ $kpiLabel }}">✅ Respondidas</div>
            <div style="{{ $kpiValue }} color: rgb(16,185,129);">{{ $stats['answered'] }}</div>
            <div style="font-size: 0.75rem; opacity: .7;">{{ $responseRate }}% de respuesta</div>
        </x-filament::section>

        <x-filament::section compact>
            <div style="{{ $kpiLabel }}">⏳ Pendientes</div>
            <div style="{{ $kpiValue }} color: rgb(245,158,11);">{{ $stats['pending'] }}</div>
            <div style="font-size: 0.75rem; opacity: .7;">Sin respuesta del usuario</div>
        </x-filament::section>

        <x-filament::section compact>
            <div style="{{ $kpiLabel }}">⭐ Calificación media</div>
            <div style="{{ $kpiValue }}">{{ $stats['avg'] !== null ? $stats['avg'].' / 5' : '—' }}</div>
            @if ($stats['avg'] !== null)
                <div style="font-size: 1rem; letter-spacing: .1em; color: rgb(245,158,11);">
                    {{ str_repeat('★', (int) round($stats['avg'])) }}<span style="opacity: .3;">{{ str_repeat('★', 5 - (int) round($stats['avg'])) }}</span>
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Tabla --}}
    <x-filament::section icon="heroicon-o-chat-bubble-bottom-center-text">
        <x-slot name="heading">Encuestas ({{ $rows->count() }})</x-slot>
        <x-slot name="description">Tickets y mantenimientos juntos; la etiqueta indica el origen. Clic en el número para abrirlo.</x-slot>

        @if ($rows->isEmpty())
            <div style="padding: 2rem; text-align: center; opacity: .7;">
                <div style="font-size: 2rem;">🗂️</div>
                No hay encuestas en el periodo seleccionado con esos filtros.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(128,128,128,.25);">
                            <th style="{{ $th }}">Origen</th>
                            <th style="{{ $th }}">Relacionado con</th>
                            <th style="{{ $th }}">Departamento</th>
                            <th style="{{ $th }}">Usuario</th>
                            <th style="{{ $th }}">Calificación</th>
                            <th style="{{ $th }}">Comentario</th>
                            <th style="{{ $th }}">Respondida</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr style="border-bottom: 1px solid rgba(128,128,128,.12);">
                                <td style="{{ $td }}">
                                    @if ($row['source'] === 'ticket')
                                        <x-filament::badge color="info" icon="heroicon-m-ticket">Ticket</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning" icon="heroicon-m-wrench-screwdriver">Mantenimiento</x-filament::badge>
                                    @endif
                                </td>
                                <td style="{{ $td }} white-space: nowrap;">
                                    @if ($row['related_url'])
                                        <x-filament::link :href="$row['related_url']" wire:navigate>{{ $row['related_label'] }}</x-filament::link>
                                    @else
                                        <span style="opacity: .7;">{{ $row['related_label'] }}</span>
                                    @endif
                                </td>
                                <td style="{{ $td }}">{{ $row['department'] ?? '—' }}</td>
                                <td style="{{ $td }}">{{ $row['user_name'] ?? '—' }}</td>
                                <td style="{{ $td }} white-space: nowrap;">
                                    @if ($row['rating'])
                                        <span style="color: rgb(245,158,11); letter-spacing: .05em;">{{ str_repeat('★', $row['rating']) }}</span><span style="opacity: .3;">{{ str_repeat('★', 5 - $row['rating']) }}</span>
                                    @else
                                        <x-filament::badge color="gray">Pendiente</x-filament::badge>
                                    @endif
                                </td>
                                <td style="{{ $td }} max-width: 28rem;">
                                    @if ($row['comment'])
                                        <span style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;" title="{{ $row['comment'] }}">“{{ $row['comment'] }}”</span>
                                    @else
                                        <span style="opacity: .5;">—</span>
                                    @endif
                                </td>
                                <td style="{{ $td }} white-space: nowrap; opacity: .8;">
                                    {{ $row['responded_at']?->translatedFormat('d M Y H:i') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
