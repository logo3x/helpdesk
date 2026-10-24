<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Helpdesk Confipetrol — pide ayuda técnica, sigue tu caso en tiempo real y consulta el centro de ayuda. Rápido y sin correos.">
    <meta name="theme-color" content="#f8fafc">

    <title>Helpdesk · Confipetrol</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --brand:        #0b4b73;
            --brand-dark:   #083956;
            --brand-tint:   #e8f0f6;
            --ink:          #0f172a;
            --ink-2:        #334155;
            --ink-3:        #64748b;
            --rule:         #e2e8f0;
            --rule-strong:  #cbd5e1;
            --surface:      #ffffff;
            --surface-alt:  #f8fafc;
            --success:      #047857;
            --success-tint: #ecfdf5;
        }

        .dark {
            color-scheme: dark;
            --ink:          #e2e8f0;
            --ink-2:        #94a3b8;
            --ink-3:        #64748b;
            --rule:         #1e293b;
            --rule-strong:  #334155;
            --surface:      #0f172a;
            --surface-alt:  #0b1220;
            --brand-tint:   #0e2a3d;
        }

        html, body { overflow-x: clip; }
        body {
            font-family: 'IBM Plex Sans', system-ui, -apple-system, Segoe UI, sans-serif;
            background: var(--surface);
            color: var(--ink);
            font-feature-settings: 'kern', 'liga';
        }

        /* ── Botones corporativos: lisos, sin glow ──────────────── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--brand);
            color: #fff;
            border: 1px solid var(--brand);
            border-radius: 6px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease;
        }
        .btn-primary:hover  { background: var(--brand-dark); border-color: var(--brand-dark); }
        .btn-primary:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: var(--ink);
            border: 1px solid var(--rule-strong);
            border-radius: 6px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease;
        }
        .btn-secondary:hover { background: var(--surface-alt); border-color: var(--ink-3); }
        .btn-secondary:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

        /* ── Pleca institucional (línea sobre títulos de sección) ── */
        .eyebrow {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand);
        }
        .dark .eyebrow { color: #7cb3d9; }

        .section-title {
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 600;
            letter-spacing: -0.015em;
            color: var(--ink);
            line-height: 1.2;
        }

        /* ── Cards sobrios ──────────────────────────────────────── */
        .card {
            background: var(--surface);
            border: 1px solid var(--rule);
            border-radius: 8px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .card:hover { border-color: var(--rule-strong); }

        .card-dark {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 8px;
        }

        /* ── Workflow stage ──────────────────────────────────────── */
        .stage {
            display: grid;
            grid-template-columns: 72px 1fr;
            gap: 2rem;
            align-items: start;
            padding: 2.5rem 0;
            border-top: 1px solid var(--rule);
        }
        @media (max-width: 640px) {
            .stage { grid-template-columns: 1fr; gap: 0.75rem; padding: 1.75rem 0; }
        }
        .stage-number {
            font-size: 1rem;
            font-weight: 600;
            color: var(--brand);
            letter-spacing: 0.05em;
            padding-top: 0.25rem;
        }
        .dark .stage-number { color: #7cb3d9; }

        /* ── Service item ────────────────────────────────────────── */
        .service-item {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            padding: 1.25rem;
            background: var(--surface);
            border: 1px solid var(--rule);
            border-radius: 8px;
            transition: border-color .15s ease;
        }
        .service-item:hover { border-color: var(--rule-strong); }

        .service-icon {
            flex-shrink: 0;
            width: 36px; height: 36px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--brand-tint);
            border-radius: 6px;
            color: var(--brand);
        }
        .dark .service-icon { color: #7cb3d9; }

        /* ── Entradas simples (sin animaciones de brinco) ────────── */
        @media (prefers-reduced-motion: no-preference) {
            .fade-in { animation: fade-in .4s ease both; }
            @keyframes fade-in {
                from { opacity: 0; transform: translateY(6px); }
                to   { opacity: 1; transform: translateY(0); }
            }
        }
    </style>
</head>
<body class="antialiased selection:bg-slate-200 selection:text-slate-900 dark:selection:bg-slate-700 dark:selection:text-slate-50">

{{-- ═══════════════════════════════════════════════
     NAV — barra institucional
     ═══════════════════════════════════════════════ --}}
<header class="sticky top-0 z-50 border-b border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur">
    <nav aria-label="Navegación principal"
         class="mx-auto max-w-6xl flex items-center justify-between px-6 lg:px-8 h-16">

        <a href="/" aria-label="Helpdesk Confipetrol — Inicio" class="flex items-center gap-3">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="" aria-hidden="true"
                 class="h-7 w-auto dark:hidden">
            <img src="{{ asset('images/logo-confipetrol.png') }}" alt="" aria-hidden="true"
                 class="h-7 w-auto hidden dark:block">
            <span class="hidden sm:block text-xs font-semibold tracking-wider uppercase text-slate-500 dark:text-slate-400 pl-3 border-l border-slate-200 dark:border-slate-800">
                Helpdesk
            </span>
        </a>

        <div class="hidden md:flex items-center gap-8 text-sm text-slate-600 dark:text-slate-300">
            <a href="#beneficios"    class="hover:text-slate-900 dark:hover:text-white transition-colors">Beneficios</a>
            <a href="#como-funciona" class="hover:text-slate-900 dark:hover:text-white transition-colors">Cómo funciona</a>
            <a href="#servicios"     class="hover:text-slate-900 dark:hover:text-white transition-colors">Servicios</a>
        </div>

        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary">
                    Ir a mis tickets
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-primary">
                    Ingresar
                </a>
            @endauth
        </div>
    </nav>
</header>

<main>

    {{-- ═══════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════ --}}
    <section class="bg-white dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
        <div class="mx-auto max-w-6xl px-6 lg:px-8 py-16 md:py-24">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">

                {{-- Texto --}}
                <div class="fade-in">
                    <span class="eyebrow">Soporte técnico interno · Confipetrol</span>

                    <h1 class="mt-6 text-slate-900 dark:text-white leading-[1.1] font-semibold tracking-tight"
                        style="font-size: clamp(2rem, 4vw, 3rem);">
                        ¿Tienes un problema técnico? Lo resolvemos.
                    </h1>

                    <p class="mt-5 text-base lg:text-lg text-slate-600 dark:text-slate-300 leading-relaxed max-w-xl">
                        Una sola plataforma para reportar fallas, seguir tu caso
                        y consultar la base de conocimiento de TI.
                        Sin correos, sin llamadas, sin espera.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary">
                                Ver mis solicitudes
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary">
                                Pedir ayuda ahora
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                            @if(config('services.azure.client_id'))
                                <a href="{{ route('auth.azure') }}" class="btn-secondary">
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 23 23" fill="currentColor" aria-hidden="true"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                                    Entrar con Azure
                                </a>
                            @endif
                        @endauth
                    </div>

                    <p class="mt-6 text-xs text-slate-500 dark:text-slate-400">
                        Accede con tu correo corporativo · Tu cuenta ya existe
                    </p>
                </div>

                {{-- Demo: formulario de solicitud --}}
                <div class="fade-in">
                    <div class="card overflow-hidden">
                        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-1.5 h-1.5 rounded-full" style="background: var(--brand);"></span>
                                <span class="text-xs font-semibold tracking-wider uppercase text-slate-600 dark:text-slate-400">Nueva solicitud</span>
                            </div>
                            <span class="text-xs text-slate-400 dark:text-slate-500">Helpdesk</span>
                        </div>

                        <div class="p-5 space-y-4 bg-white dark:bg-slate-900">
                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Tipo de problema</label>
                                <div class="flex items-center gap-2 px-3 py-2.5 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-700 dark:text-slate-200">
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Computador / hardware
                                    <svg class="w-3.5 h-3.5 ml-auto text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">Descripción</label>
                                <div class="px-3 py-2.5 rounded border text-sm text-slate-700 dark:text-slate-200 leading-relaxed"
                                     style="border-color: var(--brand); background: var(--brand-tint);">
                                    Mi computador no se conecta al wifi desde esta mañana. Probé reiniciarlo y sigue igual.
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <span class="text-xs text-slate-500 dark:text-slate-400">Tu agente responde pronto</span>
                                <span class="inline-flex items-center gap-2 rounded px-4 py-2 text-sm font-medium text-white" style="background: var(--brand);">
                                    Enviar
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400">
                        Así se ve cuando abres una solicitud
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         BENEFICIOS — sección oscura corporativa
         ═══════════════════════════════════════════════ --}}
    <section id="beneficios" class="bg-slate-950 py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">

            <div class="mb-12 max-w-2xl">
                <span class="eyebrow" style="color: #7cb3d9;">Por qué usarlo</span>
                <h2 class="mt-4 section-title text-white">
                    Más rápido que un correo. Más claro que una llamada.
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <div class="card-dark p-6">
                    <div class="mb-5 w-10 h-10 rounded flex items-center justify-center" style="background: #1e293b;">
                        <svg class="w-5 h-5" style="color: #7cb3d9;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2 leading-snug">
                        Siempre sabes dónde está tu caso
                    </h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Cada solicitud tiene un estado visible en tiempo real: si está asignada, en progreso o resuelta.
                        No tienes que preguntar "¿alguien vio mi correo?".
                    </p>
                </div>

                <div class="card-dark p-6">
                    <div class="mb-5 w-10 h-10 rounded flex items-center justify-center" style="background: #1e293b;">
                        <svg class="w-5 h-5" style="color: #7cb3d9;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2 leading-snug">
                        Tu historial nunca desaparece
                    </h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Todos tus casos quedan guardados con la conversación completa y lo que se hizo para resolverlos.
                        Si el problema regresa, el agente ya sabe el contexto.
                    </p>
                </div>

                <div class="card-dark p-6">
                    <div class="mb-5 w-10 h-10 rounded flex items-center justify-center" style="background: #1e293b;">
                        <svg class="w-5 h-5" style="color: #7cb3d9;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2 leading-snug">
                        Tu solicitud no se puede perder
                    </h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        El sistema asigna automáticamente tu caso al equipo correcto y monitorea que tenga respuesta.
                        Si pasa demasiado tiempo sin atención, se escala solo.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">

                <div class="card-dark p-6 flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded flex items-center justify-center mt-0.5" style="background: #1e293b;">
                        <svg class="w-5 h-5" style="color: #7cb3d9;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white mb-1.5 leading-snug">
                            Centro de ayuda con asistente IA
                        </h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            Antes de abrir un ticket, consulta el centro de ayuda. Hay artículos escritos por el equipo de TI
                            y un asistente que responde preguntas frecuentes al instante — sin esperar a que nadie conteste.
                        </p>
                    </div>
                </div>

                <div class="card-dark p-6 flex gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded flex items-center justify-center mt-0.5" style="background: #1e293b;">
                        <svg class="w-5 h-5" style="color: #7cb3d9;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-white mb-1.5 leading-snug">
                            Tus equipos asignados, siempre visibles
                        </h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            En "Mis activos" puedes ver los equipos que TI tiene registrados a tu nombre,
                            aceptar formalmente la entrega y descargar el acta si la necesitas.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         CÓMO FUNCIONA — 3 etapas lineales
         ═══════════════════════════════════════════════ --}}
    <section id="como-funciona" class="bg-white dark:bg-slate-950 py-20 lg:py-28">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">

            <div class="mb-10 max-w-2xl">
                <span class="eyebrow">El proceso</span>
                <h2 class="mt-4 section-title">
                    Así funciona para ti.
                </h2>
            </div>

            {{-- Etapa 01 --}}
            <div class="stage">
                <div class="stage-number">01</div>
                <div>
                    <h3 class="text-xl sm:text-2xl font-semibold text-slate-900 dark:text-white mb-3 leading-snug">
                        Abres tu solicitud
                    </h3>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed mb-5 max-w-2xl">
                        Inicia sesión con tu correo de Confipetrol, describe el problema con tus propias palabras
                        y selecciona la categoría. El sistema lo enruta al equipo correcto de forma automática.
                        Todo el proceso toma menos de dos minutos.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded px-3 py-1 text-xs font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900">
                            <svg class="w-3 h-3 shrink-0" style="color: var(--brand);" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Sin instalar nada
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded px-3 py-1 text-xs font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900">
                            <svg class="w-3 h-3 shrink-0" style="color: var(--brand);" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Desde cualquier dispositivo
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded px-3 py-1 text-xs font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900">
                            <svg class="w-3 h-3 shrink-0" style="color: var(--brand);" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Adjunta capturas o fotos
                        </span>
                    </div>
                </div>
            </div>

            {{-- Etapa 02 --}}
            <div class="stage">
                <div class="stage-number">02</div>
                <div>
                    <h3 class="text-xl sm:text-2xl font-semibold text-slate-900 dark:text-white mb-3 leading-snug">
                        Tu agente lo toma y actúa
                    </h3>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed mb-5 max-w-2xl">
                        Un agente del área correspondiente recibe tu caso y te escribe para confirmarte que lo tomó.
                        Puedes responderle directamente desde la plataforma como si fuera un chat.
                        Ves en tiempo real si el caso está siendo trabajado.
                    </p>

                    <div class="card max-w-md">
                        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
                            <span class="inline-block w-1.5 h-1.5 rounded-full" style="background: var(--brand);"></span>
                            <span class="text-xs font-semibold tracking-wider text-slate-500 dark:text-slate-400 uppercase">Caso #TK-2481 · En progreso</span>
                        </div>
                        <div class="p-4 space-y-3 bg-white dark:bg-slate-900">
                            <div class="flex gap-2.5">
                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-semibold text-white bg-slate-700 dark:bg-slate-600">A</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 mb-0.5">Agente TI</p>
                                    <div class="bg-slate-50 dark:bg-slate-800 rounded px-3 py-2 text-sm text-slate-700 dark:text-slate-200 leading-snug border border-slate-200 dark:border-slate-700">
                                        Hola, tomé tu caso. ¿El wifi no aparece en la lista de redes o aparece pero no conecta?
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2.5 flex-row-reverse">
                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-semibold text-white" style="background: var(--brand);">M</div>
                                <div class="flex-1 min-w-0 text-right">
                                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 mb-0.5">Tú</p>
                                    <div class="inline-block text-left rounded px-3 py-2 text-sm leading-snug text-white" style="background: var(--brand);">
                                        Aparece pero dice "sin internet"
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Etapa 03 --}}
            <div class="stage" style="border-bottom: 1px solid var(--rule);">
                <div class="stage-number">03</div>
                <div>
                    <h3 class="text-xl sm:text-2xl font-semibold text-slate-900 dark:text-white mb-3 leading-snug">
                        Problema resuelto y registrado
                    </h3>
                    <p class="text-slate-600 dark:text-slate-300 leading-relaxed mb-5 max-w-2xl">
                        Cuando tu caso se cierra, recibes una notificación con el resumen de lo que se hizo.
                        Puedes calificar la atención, consultar el historial de tus casos anteriores
                        o reabrir el caso si el problema regresa.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="inline-flex items-center gap-3 rounded px-4 py-3 border"
                             style="border-color: #a7f3d0; background: var(--success-tint);">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" style="background: var(--success);">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color: #065f46;">Caso #TK-2481 · Resuelto</p>
                                <p class="text-xs text-slate-600">Se actualizó el perfil de red · Hoy 14:32</p>
                            </div>
                        </div>

                        <div class="inline-flex items-center gap-3 rounded border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Califica la atención</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tu opinión mejora el servicio</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         SERVICIOS — Qué puedes pedir
         ═══════════════════════════════════════════════ --}}
    <section id="servicios" class="bg-slate-50 dark:bg-slate-900 py-20 lg:py-28 border-t border-slate-200 dark:border-slate-800">
        <div class="mx-auto max-w-6xl px-6 lg:px-8">

            <div class="mb-10">
                <span class="eyebrow">Categorías</span>
                <div class="mt-4 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <h2 class="section-title">
                        ¿Qué puedes pedir?
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 sm:text-right sm:max-w-sm leading-relaxed">
                        Si tu problema no encaja exactamente en ninguna categoría, descríbelo y lo asignamos nosotros.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <div class="service-item">
                    <div class="service-icon">
                        <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Mi computador / equipo</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Lento, no enciende, pantalla dañada, teclado o ratón con fallas</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="service-icon">
                        <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Internet y red</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Sin conexión, wifi lento, VPN, problemas de acceso remoto</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="service-icon">
                        <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Accesos y contraseñas</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Olvidé mi contraseña, no puedo entrar a un sistema, solicitar acceso nuevo</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="service-icon">
                        <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Impresoras y periféricos</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">No imprime, scanner, cámara web, monitores o dispositivos externos</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="service-icon">
                        <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Software y aplicaciones</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Instalar o actualizar un programa, error al abrir una aplicación</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="service-icon">
                        <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white mb-1">Otro problema</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Cualquier otra falla técnica — descríbela y la asignamos al área correcta</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         CTA FINAL
         ═══════════════════════════════════════════════ --}}
    <section class="bg-slate-950">
        <div class="mx-auto max-w-6xl px-6 lg:px-8 py-14 lg:py-16">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-8">
                <div>
                    <h2 class="text-xl sm:text-2xl font-semibold text-white mb-2 leading-snug">
                        ¿Tienes un problema ahora mismo?
                    </h2>
                    <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                        Inicia sesión con tu cuenta de Confipetrol y abre una solicitud.
                        Menos de dos minutos. Sin llamadas. Sin correos.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary justify-center">
                            Ir a mis tickets
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary justify-center">
                            Pedir ayuda
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        @if(config('services.azure.client_id'))
                            <a href="{{ route('auth.azure') }}"
                               class="inline-flex items-center justify-center gap-2 rounded border border-slate-700 bg-slate-900 hover:bg-slate-800 px-5 py-2.5 text-sm font-medium text-slate-200 whitespace-nowrap transition">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 23 23" fill="currentColor" aria-hidden="true"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                                Azure AD
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         FOOTER
         ═══════════════════════════════════════════════ --}}
    <footer class="bg-slate-950 border-t border-slate-900">
        <div class="mx-auto max-w-6xl px-6 lg:px-8 py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="h-5 w-auto opacity-60">
                <span class="text-xs tracking-wider uppercase text-slate-500">
                    Helpdesk · Uso interno · &copy; {{ date('Y') }}
                </span>
            </div>
            <div class="flex items-center gap-6 text-xs tracking-wider uppercase text-slate-500">
                <a href="#beneficios"    class="hover:text-slate-300 transition-colors">Beneficios</a>
                <a href="#como-funciona" class="hover:text-slate-300 transition-colors">Cómo funciona</a>
                <a href="#servicios"     class="hover:text-slate-300 transition-colors">Servicios</a>
                <a href="{{ route('login') }}" class="hover:text-slate-300 transition-colors">Ingresar</a>
            </div>
        </div>
    </footer>

</main>
</body>
</html>
