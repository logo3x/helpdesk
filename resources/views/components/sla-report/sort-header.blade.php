@props([
    'table',
    'column',
    'label',
    'sort' => ['column' => '', 'direction' => 'asc'],
    'align' => 'left',
    'tip' => null,
])

@php($isActive = ($sort['column'] ?? '') === $column)

<th {{ $attributes->class(['px-3 py-2.5 font-semibold', 'text-left' => $align === 'left', 'text-center' => $align === 'center', 'text-right' => $align === 'right']) }}
    @if ($isActive) aria-sort="{{ $sort['direction'] === 'desc' ? 'descending' : 'ascending' }}" @endif>
    <span class="inline-flex items-center gap-0.5 {{ $align === 'right' ? 'flex-row-reverse' : '' }}">
        <button type="button"
                wire:click="sortTable('{{ $table }}', '{{ $column }}')"
                class="sla-sort-btn {{ $isActive ? 'is-active' : '' }}"
                title="Ordenar por {{ mb_strtolower($label) }}">
            {{ $label }}
            <span class="sla-sort-arrow">{{ $isActive ? ($sort['direction'] === 'desc' ? '▼' : '▲') : '↕' }}</span>
        </button>
        @if ($tip)
            <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="{{ $tip }}" style="width:0.95rem;height:0.95rem;" />
        @endif
    </span>
</th>
