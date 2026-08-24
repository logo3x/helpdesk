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

            {{-- Cards de trabajo pendiente + accesos rápidos --}}
            <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                {{-- Tickets --}}
                <div style="background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); border-radius:.75rem; padding:.6rem 1rem; text-align:center; min-width:90px;">
                    <p style="font-size:1.5rem; font-weight:700; color:#fff; margin:0; line-height:1;">{{ $data['myOpen'] }}</p>
                    <p style="font-size:.7rem; color:#94a3b8; margin:3px 0 0;">
                        {{ $data['myOpen'] === 1 ? 'ticket abierto' : 'tickets abiertos' }} a tu cargo
                    </p>
                </div>
                {{-- Link con filtro "asignados a mí" activo — así el
                     agente ve exactamente los N que muestra la card. --}}
                <a href="{{ route('filament.soporte.resources.tickets.index', ['tableFilters' => ['only_open' => ['isActive' => '1'], 'assigned_to_me' => ['isActive' => '1']]]) }}"
                   style="display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1rem; border-radius:.75rem; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.1); color:#fff; font-size:.8rem; font-weight:500; text-decoration:none; transition:background .15s;"
                   onmouseover="this.style.background='rgba(255,255,255,.2)'"
                   onmouseout="this.style.background='rgba(255,255,255,.1)'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;opacity:.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" /></svg>
                    Ver mis tickets
                </a>

                @if ($data['showMaintenances'])
                    {{-- Mantenimientos programados asignados --}}
                    @php
                        $mCount = $data['myMaintenancesOpen'];
                        $mOverdue = $data['myMaintenancesOverdue'];
                        // Si hay vencidos, borde rojo. Si hay pendientes pero no vencidos, ámbar sutil. Si 0, gris.
                        $mBorderColor = $mOverdue > 0
                            ? 'rgba(248,113,113,.55)'
                            : ($mCount > 0 ? 'rgba(251,191,36,.35)' : 'rgba(255,255,255,.12)');
                        $mBg = $mOverdue > 0
                            ? 'rgba(248,113,113,.12)'
                            : 'rgba(255,255,255,.07)';
                    @endphp
                    <div style="background:{{ $mBg }}; border:1px solid {{ $mBorderColor }}; border-radius:.75rem; padding:.6rem 1rem; text-align:center; min-width:100px;">
                        <p style="font-size:1.5rem; font-weight:700; color:#fff; margin:0; line-height:1;">{{ $mCount }}</p>
                        <p style="font-size:.7rem; color:#94a3b8; margin:3px 0 0;">
                            {{ $mCount === 1 ? 'mantenimiento' : 'mantenimientos' }} asignados
                        </p>
                        @if ($mOverdue > 0)
                            <p style="font-size:.65rem; color:#fca5a5; margin:2px 0 0; font-weight:600;">
                                ⚠ {{ $mOverdue }} vencido{{ $mOverdue === 1 ? '' : 's' }}
                            </p>
                        @endif
                    </div>
                    <a href="{{ route('filament.soporte.resources.scheduled-maintenances.index') }}"
                       style="display:inline-flex; align-items:center; gap:.4rem; padding:.6rem 1rem; border-radius:.75rem; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.1); color:#fff; font-size:.8rem; font-weight:500; text-decoration:none; transition:background .15s;"
                       onmouseover="this.style.background='rgba(255,255,255,.2)'"
                       onmouseout="this.style.background='rgba(255,255,255,.1)'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem;opacity:.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" /></svg>
                        Ver mantenimientos
                    </a>
                @endif
            </div>
        </div>

        {{-- Fecha --}}
        <div style="border-top:1px solid rgba(255,255,255,.08); padding:.4rem 1.5rem; font-size:.7rem; color:#64748b;">
            {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
        </div>
    </div>
</x-filament-widgets::widget>
