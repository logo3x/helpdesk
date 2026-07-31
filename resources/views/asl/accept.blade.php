<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <title>Acuerdo de uso — Helpdesk Confipetrol</title>
    <style>
        :root {
            --ink: #1c1917;
            --paper: #f8f8f8;
            --line: #e5e5e5;
            --accent: #e07b39;
            --accent-soft: #fff7f0;
            --accent-dark: #c4622a;
            --muted: #6b7280;
            --check: #2563eb;
            --check-soft: #eff6ff;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--paper);
            color: var(--ink);
            font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .asl-wrap {
            width: 100%;
            max-width: 640px;
        }

        .asl-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .asl-logo img { height: 2.75rem; width: auto; object-fit: contain; }

        .asl-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }

        .asl-head {
            padding: 1.75rem 2rem 1.5rem;
            border-bottom: 1px solid var(--line);
            background: var(--paper);
        }

        .asl-eyebrow {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--accent);
            margin: 0 0 0.5rem;
        }

        .asl-head h1 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.375rem;
            line-height: 1.3;
        }

        .asl-head p {
            font-size: 0.875rem;
            color: var(--muted);
            margin: 0;
            line-height: 1.5;
        }

        .asl-body { padding: 0.25rem 2rem 0; }

        .asl-clause {
            display: flex;
            gap: 0.875rem;
            padding: 1.125rem 0;
            border-bottom: 1px solid var(--line);
            align-items: flex-start;
        }

        .asl-clause:last-child { border-bottom: none; }

        .asl-clause input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 1.125rem;
            height: 1.125rem;
            border: 1.5px solid #a8a29e;
            border-radius: 0.25rem;
            cursor: pointer;
            display: grid;
            place-content: center;
            background: #fff;
            flex-shrink: 0;
            margin-top: 0.125rem;
            transition: border-color 0.12s, background 0.12s;
        }

        .asl-clause input[type="checkbox"]::after {
            content: "";
            width: 0.625rem;
            height: 0.625rem;
            transform: scale(0);
            transition: transform 0.1s ease;
            background: var(--check);
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 45% 62%);
        }

        .asl-clause input[type="checkbox"]:checked {
            border-color: var(--check);
            background: var(--check-soft);
        }

        .asl-clause input[type="checkbox"]:checked::after { transform: scale(1); }

        .asl-clause input[type="checkbox"]:focus-visible {
            outline: 2px solid var(--check);
            outline-offset: 2px;
        }

        .asl-clause-text { flex: 1; min-width: 0; }

        .asl-clause-text label {
            font-size: 0.875rem;
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
            cursor: pointer;
            line-height: 1.4;
        }

        .asl-clause-text p {
            font-size: 0.8125rem;
            color: var(--muted);
            margin: 0;
            line-height: 1.55;
        }

        .asl-view-btn {
            background: none;
            border: none;
            padding: 0;
            margin-top: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent);
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
            font-family: inherit;
            display: inline-block;
        }

        .asl-footer {
            padding: 1.25rem 2rem 1.75rem;
            background: var(--paper);
            border-top: 1px solid var(--line);
        }

        .asl-progress-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .asl-progress-label {
            font-size: 0.75rem;
            color: var(--muted);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .asl-progress-track {
            flex: 1;
            height: 3px;
            background: var(--line);
            border-radius: 2px;
            overflow: hidden;
        }

        .asl-progress-fill {
            height: 100%;
            background: var(--check);
            width: 0%;
            transition: width 0.2s ease;
        }

        .asl-btn-accept {
            width: 100%;
            padding: 0.8125rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.12s;
        }

        .asl-btn-accept:disabled {
            background: #d6d3d1;
            cursor: not-allowed;
        }

        .asl-btn-accept:not(:disabled):hover { background: var(--accent-dark); }

        .asl-btn-logout {
            display: block;
            width: 100%;
            text-align: center;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 0.8125rem;
            margin-top: 0.625rem;
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
            font-family: inherit;
        }

        /* Modal */
        .asl-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(28,25,23,.55);
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 1.25rem;
        }

        .asl-modal-overlay.open { display: flex; }

        .asl-modal {
            background: #fff;
            border-radius: 0.625rem;
            max-width: 520px;
            width: 100%;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
        }

        .asl-modal-head {
            padding: 1.25rem 1.5rem 1rem;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .asl-modal-head h3 { font-size: 1rem; font-weight: 700; margin: 0; }

        .asl-modal-close {
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 50%;
            width: 1.75rem;
            height: 1.75rem;
            flex-shrink: 0;
            cursor: pointer;
            font-size: 1rem;
            color: var(--muted);
            display: grid;
            place-content: center;
            line-height: 1;
        }

        .asl-modal-close:hover { color: var(--ink); }

        .asl-modal-body {
            padding: 1.125rem 1.5rem 1.5rem;
            overflow-y: auto;
            font-size: 0.8125rem;
            line-height: 1.65;
            color: #44403c;
        }

        .asl-modal-body h4 {
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--accent);
            margin: 1rem 0 0.25rem;
            font-weight: 700;
        }

        .asl-modal-body h4:first-child { margin-top: 0; }
        .asl-modal-body p { margin: 0 0 0.25rem; }
    </style>
</head>
<body>
    <div class="asl-wrap">

        {{-- Logo --}}
        <div class="asl-logo">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol" />
        </div>

        <div class="asl-card">
            <div class="asl-head">
                <p class="asl-eyebrow">Antes de continuar</p>
                <h1>Condiciones de uso del Helpdesk</h1>
                <p>Hola <strong>{{ auth()->user()->name ?? 'usuario' }}</strong>, revisa y acepta cada punto para activar tu acceso a Helpdesk Confipetrol.</p>
            </div>

            <form method="POST" action="{{ route('asl.accept') }}" id="asl-form">
                @csrf

                <div class="asl-body" id="asl-body">

                    @php
                    $clauses = [
                        [
                            'id'    => 'c1',
                            'title' => 'Términos y condiciones de uso',
                            'text'  => 'He leído y acepto los Términos y Condiciones de uso de esta aplicación.',
                            'full'  => [
                                ['h' => '1. Objeto', 'p' => 'Helpdesk Confipetrol es una herramienta interna destinada a la gestión y resolución de solicitudes de soporte técnico y operativo del personal de Confipetrol y sus contratos asociados.'],
                                ['h' => '2. Acceso personal e intransferible', 'p' => 'El acceso es personal. El usuario es responsable de las acciones realizadas con sus credenciales y debe reportar de inmediato cualquier uso no autorizado de su cuenta.'],
                                ['h' => '3. Propiedad de la información', 'p' => 'Toda la información, reportes, tickets y contenidos generados dentro de la aplicación son propiedad de Confipetrol y no pueden ser reproducidos, distribuidos ni divulgados a terceros sin autorización expresa.'],
                                ['h' => '4. Uso aceptable', 'p' => 'La aplicación debe utilizarse exclusivamente para fines relacionados con las funciones laborales del usuario. Queda prohibido el uso para actividades personales, comerciales ajenas a la organización o cualquier fin ilícito.'],
                                ['h' => '5. Confidencialidad', 'p' => 'El usuario se compromete a mantener la confidencialidad de la información técnica, operativa y de contratos a la que tenga acceso, incluso después de finalizada su relación laboral o contractual con Confipetrol.'],
                                ['h' => '6. Modificaciones', 'p' => 'Confipetrol podrá actualizar estos términos en cualquier momento. Los cambios se notificarán dentro de la aplicación y el uso continuado implica su aceptación.'],
                            ],
                        ],
                        [
                            'id'    => 'c2',
                            'title' => 'Tratamiento de datos personales',
                            'text'  => 'Autorizo el tratamiento de mis datos personales conforme a la Política de Privacidad de Confipetrol, según la Ley 1581 de 2012 (Habeas Data).',
                        ],
                        [
                            'id'    => 'c3',
                            'title' => 'Acuerdo de Nivel de Servicio (SLA)',
                            'text'  => 'Entiendo y acepto los tiempos de respuesta y atención definidos según la prioridad del ticket: Crítica (2 h), Alta (8 h), Media (24 h) y Baja (72 h hábiles).',
                            'full'  => [
                                ['h' => 'Prioridad Crítica', 'p' => 'Tiempo de primera respuesta: 2 horas hábiles. Aplica a incidentes que impiden completamente la operación de uno o más procesos críticos del negocio.'],
                                ['h' => 'Prioridad Alta', 'p' => 'Tiempo de primera respuesta: 8 horas hábiles. Aplica a fallas que afectan parcialmente la operación con impacto significativo en la productividad.'],
                                ['h' => 'Prioridad Media', 'p' => 'Tiempo de primera respuesta: 24 horas hábiles. Aplica a solicitudes que afectan al usuario pero tienen solución alternativa disponible.'],
                                ['h' => 'Prioridad Baja', 'p' => 'Tiempo de primera respuesta: 72 horas hábiles. Aplica a consultas, mejoras o solicitudes sin impacto inmediato en la operación.'],
                                ['h' => 'Disponibilidad del servicio', 'p' => 'El equipo de soporte atiende en horario laboral de lunes a viernes. Los tickets fuera de horario se atienden al siguiente día hábil.'],
                                ['h' => 'Escalonamiento', 'p' => 'Si un ticket no es resuelto en el tiempo acordado, se escala automáticamente al supervisor del área de soporte para gestión prioritaria.'],
                            ],
                        ],
                        [
                            'id'    => 'c4',
                            'title' => 'Uso adecuado y responsabilidad',
                            'text'  => 'Me comprometo a usar la aplicación de forma legal y responsable, y a no emplearla con fines fraudulentos o que vulneren derechos de terceros.',
                        ],
                        [
                            'id'    => 'c5',
                            'title' => 'Custodia de equipos',
                            'text'  => 'Reconozco que Confipetrol es el propietario de los equipos asignados bajo mi custodia, y que como colaborador no puedo utilizarlos con fines personales ni cederlos a terceros.',
                        ],
                        [
                            'id'    => 'c6',
                            'title' => 'Uso del asistente de IA',
                            'text'  => 'Entiendo que el chatbot de la aplicación usa inteligencia artificial para responder consultas de soporte. No debo incluir contraseñas, datos bancarios ni información personal sensible en mis mensajes.',
                        ],
                        [
                            'id'    => 'c7',
                            'title' => 'Sanciones por uso malintencionado',
                            'text'  => 'Entiendo que cualquier uso malintencionado de la aplicación o de los equipos puede acarrear sanciones disciplinarias internas y las responsabilidades penales que apliquen.',
                        ],
                    ];
                    @endphp

                    @foreach ($clauses as $clause)
                    <div class="asl-clause">
                        <input type="checkbox" id="{{ $clause['id'] }}" name="clauses[]" value="{{ $clause['id'] }}"
                               class="asl-cb" required>
                        <div class="asl-clause-text">
                            <label for="{{ $clause['id'] }}">{{ $clause['title'] }}</label>
                            <p>{{ $clause['text'] }}</p>
                            @if (!empty($clause['full']))
                                <button type="button" class="asl-view-btn"
                                        data-title="{{ $clause['title'] }}"
                                        data-full="{{ json_encode($clause['full']) }}">
                                    Ver detalle completo →
                                </button>
                            @endif
                        </div>
                    </div>
                    @endforeach

                </div>

                <div class="asl-footer">
                    <div class="asl-progress-row">
                        <span class="asl-progress-label" id="asl-count">0 / {{ count($clauses) }}</span>
                        <div class="asl-progress-track">
                            <div class="asl-progress-fill" id="asl-fill"></div>
                        </div>
                    </div>

                    <button type="button" class="asl-btn-accept" id="asl-submit">
                        Aceptar todas y continuar
                    </button>

                    <button type="button" class="asl-btn-logout" id="asl-logout-btn">
                        No acepto — cerrar sesión
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Formulario logout oculto --}}
    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    {{-- Modal detalle --}}
    <div class="asl-modal-overlay" id="asl-modal" role="dialog" aria-modal="true">
        <div class="asl-modal">
            <div class="asl-modal-head">
                <h3 id="asl-modal-title">Detalle</h3>
                <button type="button" class="asl-modal-close" id="asl-modal-close" aria-label="Cerrar">✕</button>
            </div>
            <div class="asl-modal-body" id="asl-modal-body"></div>
        </div>
    </div>

    <script>
    (function () {
        const total    = document.querySelectorAll('.asl-cb').length;
        const countEl  = document.getElementById('asl-count');
        const fillEl   = document.getElementById('asl-fill');
        const submitEl = document.getElementById('asl-submit');

        function updateProgress() {
            const checked = document.querySelectorAll('.asl-cb:checked').length;
            countEl.textContent = checked + ' / ' + total;
            fillEl.style.width  = (checked / total * 100) + '%';
        }

        document.querySelectorAll('.asl-cb').forEach(cb =>
            cb.addEventListener('change', updateProgress)
        );

        // Aceptar todas: marca todos los checkboxes y envía el formulario
        submitEl.addEventListener('click', function () {
            document.querySelectorAll('.asl-cb').forEach(cb => { cb.checked = true; });
            updateProgress();
            document.getElementById('asl-form').submit();
        });

        // Logout
        document.getElementById('asl-logout-btn').addEventListener('click', function () {
            document.getElementById('logout-form').submit();
        });

        // Modal
        const overlay   = document.getElementById('asl-modal');
        const modalTitle = document.getElementById('asl-modal-title');
        const modalBody  = document.getElementById('asl-modal-body');

        document.querySelectorAll('.asl-view-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const sections = JSON.parse(this.dataset.full);
                modalTitle.textContent = this.dataset.title;
                modalBody.innerHTML = sections.map(s =>
                    '<h4>' + s.h + '</h4><p>' + s.p + '</p>'
                ).join('');
                overlay.classList.add('open');
            });
        });

        function closeModal() { overlay.classList.remove('open'); }

        document.getElementById('asl-modal-close').addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    })();
    </script>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
