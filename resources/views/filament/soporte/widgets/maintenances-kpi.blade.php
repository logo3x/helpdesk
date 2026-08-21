{{-- KPIs compactos del módulo Mantenimientos Programados.
     Cards muy pequeñas (padding 8px, altura ~54px), 6 en una fila. --}}
<x-filament-widgets::widget>
    <style>
        .mk-grid { display:grid; gap:0.5rem; grid-template-columns:repeat(2, minmax(0,1fr)); }
        @media (min-width:768px) { .mk-grid { grid-template-columns:repeat(3, minmax(0,1fr)); } }
        @media (min-width:1200px) { .mk-grid { grid-template-columns:repeat(6, minmax(0,1fr)); } }

        .mk-card {
            display:flex; align-items:center; gap:0.5rem;
            background:white;
            border:1px solid rgb(228 228 231);
            border-radius:0.5rem;
            padding:0.5rem 0.65rem;
            min-height:2.75rem;
        }
        .dark .mk-card { background:rgb(24 24 27); border-color:rgb(63 63 70); }

        .mk-icon {
            width:1.5rem; height:1.5rem; border-radius:0.375rem;
            display:inline-flex; align-items:center; justify-content:center;
            flex-shrink:0;
        }
        .mk-icon svg { width:0.9rem; height:0.9rem; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }

        .mk-icon-info    { background:rgb(224 242 254); color:rgb(2 132 199); }
        .mk-icon-success { background:rgb(220 252 231); color:rgb(22 163 74); }
        .mk-icon-danger  { background:rgb(254 226 226); color:rgb(220 38 38); }
        .mk-icon-warning { background:rgb(254 243 199); color:rgb(217 119 6); }
        .mk-icon-gray    { background:rgb(244 244 245); color:rgb(113 113 122); }
        .dark .mk-icon-info    { background:rgba(56,189,248,0.15); color:rgb(125 211 252); }
        .dark .mk-icon-success { background:rgba(22,163,74,0.15); color:rgb(134 239 172); }
        .dark .mk-icon-danger  { background:rgba(220,38,38,0.15); color:rgb(252 165 165); }
        .dark .mk-icon-warning { background:rgba(217,119,6,0.15); color:rgb(253 224 71); }
        .dark .mk-icon-gray    { background:rgb(63 63 70); color:rgb(212 212 216); }

        .mk-body { min-width:0; line-height:1.15; }
        .mk-value { font-size:1rem; font-weight:700; color:rgb(24 24 27); }
        .dark .mk-value { color:rgb(244 244 245); }
        .mk-label { font-size:0.65rem; color:rgb(113 113 122); text-transform:uppercase; letter-spacing:0.03em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mk-hint  { font-size:0.6rem; color:rgb(161 161 170); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    </style>

    {{-- Llamamos getKpis() directamente: en Filament v5 los widgets
         Livewire a veces no re-hidratan las variables de getViewData()
         al re-render, mostrando siempre 0. --}}
    @php($kpis = $this->getKpis())
    <div class="mk-grid">
        @foreach($kpis as $k)
            <div class="mk-card">
                <span class="mk-icon mk-icon-{{ $k['color'] }}">
                    <svg viewBox="0 0 24 24"><path d="{{ $k['icon'] }}"/></svg>
                </span>
                <div class="mk-body">
                    <div class="mk-value">{{ $k['value'] }}</div>
                    <div class="mk-label">{{ $k['label'] }}</div>
                    <div class="mk-hint">{{ $k['hint'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
