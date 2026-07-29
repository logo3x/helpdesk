<x-filament-widgets::widget>
    @php
        $data = $this->getViewData();
    @endphp

    <div style="overflow:hidden; border-radius:1rem; box-shadow:0 2px 8px rgba(0,0,0,.15); background:linear-gradient(135deg,#1e293b 0%,#334155 100%); border:1px solid rgba(255,255,255,.08);">

        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; padding:1.25rem 1.5rem;">

            {{-- Saludo --}}
            <div style="display:flex; align-items:center; gap:1rem;">
                <div style="width:3rem; height:3rem; border-radius:50%; background:rgba(255,255,255,.12); border:2px solid rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:700; color:#fff; flex-shrink:0;">
                    {{ mb_strtoupper(mb_substr($data['firstName'], 0, 1)) }}
                </div>
                <div>
                    <p style="font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; margin:0 0 2px;">
                        {{ $data['greeting'] }}
                    </p>
                    <h2 style="font-size:1.25rem; font-weight:700; color:#fff; margin:0; line-height:1.2;">
                        {{ $data['firstName'] }}
                    </h2>
                    <p style="font-size:.75rem; color:#94a3b8; margin:2px 0 0;">
                        {{ $data['roleLabel'] }}
                        @if ($data['fullName'] !== $data['firstName'])
                            · {{ $data['fullName'] }}
                        @endif
                    </p>
                </div>
            </div>

            {{-- Tickets abiertos + acceso rápido --}}
            <div style="display:flex; align-items:center; gap:.75rem;">
                <div style="background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); border-radius:.75rem; padding:.6rem 1rem; text-align:center; min-width:90px;">
                    <p style="font-size:1.5rem; font-weight:700; color:#fff; margin:0; line-height:1;">{{ $data['myOpen'] }}</p>
                    <p style="font-size:.7rem; color:#94a3b8; margin:3px 0 0;">
                        {{ $data['myOpen'] === 1 ? 'ticket abierto' : 'tickets abiertos' }} a tu cargo
                    </p>
                </div>
                <a href="{{ route('filament.soporte.resources.tickets.index') }}"
                   style="display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1rem; border-radius:.75rem; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.1); color:#fff; font-size:.8rem; font-weight:500; text-decoration:none; transition:background .15s;"
                   onmouseover="this.style.background='rgba(255,255,255,.2)'"
                   onmouseout="this.style.background='rgba(255,255,255,.1)'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;opacity:.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" /></svg>
                    Ver tickets
                </a>
            </div>
        </div>

        {{-- Fecha --}}
        <div style="border-top:1px solid rgba(255,255,255,.08); padding:.4rem 1.5rem; font-size:.7rem; color:#64748b;">
            {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
        </div>
    </div>
</x-filament-widgets::widget>
