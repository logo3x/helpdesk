{{-- Trazabilidad visual del ScheduledMaintenance.
     Recibe $record (ScheduledMaintenance). Renderiza una línea de tiempo
     vertical con los eventos del ciclo + chips con datos clave. --}}

@php
    $r = $record;
    $created = $r?->created_at;
    $updated = $r?->updated_at;
    $completed = $r?->completed_at;
    $scheduled = $r?->scheduled_at;
    $parent = $r?->parent;
    $child = $r?->children()->orderBy('scheduled_at')->first();

    // Duración
    $durationText = '—';
    if ($completed && $created) {
        $days = (int) $created->diffInDays($completed);
        $hours = (int) $created->diffInHours($completed) % 24;
        $durationText = $days > 0 ? "{$days}d {$hours}h" : "{$hours}h";
    } elseif ($scheduled) {
        if ($r->isOverdue()) {
            $durationText = '⚠ Vencido hace '.(int) $scheduled->diffInDays(now()).' días';
        } else {
            $diff = (int) now()->diffInDays($scheduled, false);
            $durationText = $diff >= 0 ? "Faltan {$diff} días" : "Atrasado {$diff} días";
        }
    }

    // Asset snapshot
    $asset = $r?->asset;
    $assetTag = $asset?->asset_tag ?: 'Sin TAG';
    $custodian = $asset?->user?->name ?? $asset?->custodian_name ?? 'Sin custodio';
    $location = collect([$asset?->management_area, $asset?->field, $asset?->location_zone])
        ->filter()
        ->implode(' · ') ?: 'Sin ubicación';
@endphp

<style>
    .mt-wrap { display:grid; gap:1rem; grid-template-columns:1fr; }
    @media (min-width:900px) { .mt-wrap { grid-template-columns:1.2fr 1fr; } }

    /* Timeline */
    .mt-timeline { position:relative; padding-left:2rem; }
    .mt-timeline::before {
        content:''; position:absolute; left:0.75rem; top:0.35rem; bottom:0.35rem;
        width:2px; background:linear-gradient(180deg, rgb(59 130 246), rgb(34 197 94), rgb(161 161 170));
        border-radius:2px;
    }
    .mt-event { position:relative; padding:0.5rem 0 0.75rem 0.25rem; }
    .mt-event::before {
        content:''; position:absolute; left:-1.5rem; top:0.75rem;
        width:1rem; height:1rem; border-radius:9999px;
        background:white; border:3px solid rgb(59 130 246);
        box-shadow:0 0 0 3px white;
    }
    .dark .mt-event::before { background:rgb(24 24 27); box-shadow:0 0 0 3px rgb(24 24 27); }
    .mt-event.success::before { border-color:rgb(34 197 94); }
    .mt-event.warning::before { border-color:rgb(217 119 6); }
    .mt-event.danger::before  { border-color:rgb(220 38 38); }
    .mt-event.muted::before   { border-color:rgb(161 161 170); }

    .mt-event-title { font-size:0.8rem; font-weight:600; color:rgb(24 24 27); letter-spacing:0.01em; }
    .dark .mt-event-title { color:rgb(244 244 245); }
    .mt-event-time  { font-size:0.75rem; color:rgb(113 113 122); margin-top:0.15rem; }
    .mt-event-hint  { font-size:0.7rem; color:rgb(161 161 170); margin-top:0.1rem; }

    /* Panel derecho: chips */
    .mt-panel {
        background:rgb(250 250 250);
        border:1px solid rgb(228 228 231);
        border-radius:0.75rem;
        padding:0.85rem;
    }
    .dark .mt-panel { background:rgba(63,63,70,0.35); border-color:rgb(63 63 70); }
    .mt-panel-title { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.06em; color:rgb(113 113 122); font-weight:600; margin-bottom:0.5rem; }

    .mt-chip {
        display:flex; align-items:flex-start; gap:0.6rem;
        padding:0.55rem 0.65rem;
        background:white;
        border:1px solid rgb(228 228 231);
        border-radius:0.55rem;
        margin-bottom:0.4rem;
    }
    .dark .mt-chip { background:rgb(24 24 27); border-color:rgb(63 63 70); }
    .mt-chip-icon {
        width:1.6rem; height:1.6rem; border-radius:0.4rem; flex-shrink:0;
        display:inline-flex; align-items:center; justify-content:center;
        background:rgb(224 242 254); color:rgb(2 132 199);
    }
    .dark .mt-chip-icon { background:rgba(56,189,248,0.15); color:rgb(125 211 252); }
    .mt-chip-icon svg { width:1rem; height:1rem; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .mt-chip-body { min-width:0; flex:1; }
    .mt-chip-label { font-size:0.65rem; text-transform:uppercase; letter-spacing:0.05em; color:rgb(113 113 122); font-weight:600; }
    .mt-chip-value { font-size:0.85rem; color:rgb(24 24 27); font-weight:500; margin-top:0.1rem; word-break:break-word; }
    .dark .mt-chip-value { color:rgb(244 244 245); }

    /* Link a otro ciclo */
    .mt-cycle-link {
        display:inline-flex; align-items:center; gap:0.4rem;
        font-size:0.78rem;
        padding:0.3rem 0.55rem;
        background:rgb(239 246 255);
        color:rgb(29 78 216);
        border:1px solid rgb(191 219 254);
        border-radius:0.35rem;
        text-decoration:none;
        margin-top:0.2rem;
    }
    .mt-cycle-link:hover { background:rgb(219 234 254); }
    .dark .mt-cycle-link { background:rgba(59,130,246,0.15); color:rgb(147 197 253); border-color:rgb(30 58 138); }

    .mt-badge {
        display:inline-block; padding:0.1rem 0.45rem; border-radius:9999px;
        font-size:0.65rem; font-weight:600; letter-spacing:0.03em;
        background:rgb(244 244 245); color:rgb(63 63 70);
        margin-left:0.3rem;
    }
    .dark .mt-badge { background:rgb(63 63 70); color:rgb(212 212 216); }
    .mt-badge.success { background:rgb(220 252 231); color:rgb(22 101 52); }
    .mt-badge.danger  { background:rgb(254 226 226); color:rgb(153 27 27); }
    .mt-badge.warning { background:rgb(254 243 199); color:rgb(146 64 14); }
</style>

<div class="mt-wrap">
    {{-- COLUMNA IZQUIERDA: TIMELINE --}}
    <div class="mt-timeline">
        {{-- Programado por --}}
        <div class="mt-event">
            <div class="mt-event-title">🗓 Programado por {{ $r?->createdBy?->name ?? '—' }}</div>
            <div class="mt-event-time">{{ $created?->translatedFormat('d M Y H:i') ?? '—' }}</div>
            <div class="mt-event-hint">Registro <b>#{{ $r?->id }}</b> · Frecuencia: {{ $r?->frequency?->label() ?? '—' }}</div>
        </div>

        {{-- Fecha programada --}}
        <div class="mt-event {{ $r?->isOverdue() ? 'danger' : 'warning' }}">
            <div class="mt-event-title">📅 Fecha de ejecución</div>
            <div class="mt-event-time">{{ $scheduled?->translatedFormat('d M Y') ?? '—' }}</div>
            <div class="mt-event-hint">{{ $durationText }}</div>
        </div>

        {{-- Última actualización (solo si difiere de created) --}}
        @if ($updated && $created && $updated->gt($created->copy()->addMinute()))
            <div class="mt-event muted">
                <div class="mt-event-title">✏ Última actualización</div>
                <div class="mt-event-time">{{ $updated->translatedFormat('d M Y H:i') }}</div>
                <div class="mt-event-hint">{{ $updated->diffForHumans() }}</div>
            </div>
        @endif

        {{-- Cierre --}}
        @if ($completed)
            <div class="mt-event {{ $r->status?->value === 'no_cumplido' ? 'danger' : 'success' }}">
                <div class="mt-event-title">
                    {{ $r->status?->value === 'no_cumplido' ? '✗ Cerrado como NO cumplido' : '✓ Cerrado como cumplido' }}
                </div>
                <div class="mt-event-time">{{ $completed->translatedFormat('d M Y H:i') }} · {{ $completed->diffForHumans() }}</div>
                <div class="mt-event-hint">Avance final: {{ $r?->progress_percent ?? 0 }}%</div>
            </div>
        @else
            <div class="mt-event muted">
                <div class="mt-event-title">⏳ Aún no cerrado</div>
                <div class="mt-event-time">Estado: {{ $r?->status?->label() ?? '—' }}</div>
                <div class="mt-event-hint">Avance actual: {{ $r?->progress_percent ?? 0 }}%</div>
            </div>
        @endif
    </div>

    {{-- COLUMNA DERECHA: DATOS + CADENA --}}
    <div>
        {{-- Panel Cadena de ciclos --}}
        <div class="mt-panel" style="margin-bottom:0.75rem;">
            <div class="mt-panel-title">Cadena de ciclos</div>

            <div class="mt-chip">
                <span class="mt-chip-icon">
                    <svg viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
                </span>
                <div class="mt-chip-body">
                    <div class="mt-chip-label">Ciclo anterior</div>
                    <div class="mt-chip-value">
                        @if ($parent)
                            #{{ $parent->id }} — {{ $parent->scheduled_at->translatedFormat('d M Y') }}
                            <span class="mt-badge {{ $parent->status?->color() === 'success' ? 'success' : ($parent->status?->color() === 'danger' ? 'danger' : '') }}">
                                {{ $parent->status?->label() }}
                            </span>
                            <br>
                            <a class="mt-cycle-link" href="{{ route('filament.soporte.resources.scheduled-maintenances.edit', ['record' => $parent->id]) }}">Abrir ciclo anterior →</a>
                        @else
                            <span style="color:rgb(113 113 122);">Este es el primer ciclo</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-chip">
                <span class="mt-chip-icon">
                    <svg viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                </span>
                <div class="mt-chip-body">
                    <div class="mt-chip-label">Ciclo siguiente</div>
                    <div class="mt-chip-value">
                        @if ($child)
                            #{{ $child->id }} — {{ $child->scheduled_at->translatedFormat('d M Y') }}
                            <span class="mt-badge {{ $child->status?->color() === 'success' ? 'success' : ($child->status?->color() === 'danger' ? 'danger' : '') }}">
                                {{ $child->status?->label() }}
                            </span>
                            <br>
                            <a class="mt-cycle-link" href="{{ route('filament.soporte.resources.scheduled-maintenances.edit', ['record' => $child->id]) }}">Abrir siguiente ciclo →</a>
                        @else
                            <span style="color:rgb(113 113 122);">Se generará al cerrar este mantenimiento</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Activo --}}
        <div class="mt-panel">
            <div class="mt-panel-title">Activo (estado actual)</div>
            <div class="mt-chip">
                <span class="mt-chip-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                </span>
                <div class="mt-chip-body">
                    <div class="mt-chip-label">TAG · Tipo</div>
                    <div class="mt-chip-value">{{ $assetTag }} · {{ strtoupper($asset?->type ?? '—') }}</div>
                </div>
            </div>
            <div class="mt-chip">
                <span class="mt-chip-icon">
                    <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </span>
                <div class="mt-chip-body">
                    <div class="mt-chip-label">Custodio</div>
                    <div class="mt-chip-value">{{ $custodian }}</div>
                </div>
            </div>
            <div class="mt-chip">
                <span class="mt-chip-icon">
                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <div class="mt-chip-body">
                    <div class="mt-chip-label">Ubicación</div>
                    <div class="mt-chip-value">{{ $location }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
