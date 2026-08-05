<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Helpdesk Confipetrol — plataforma interna de soporte técnico, gestión de activos y base de conocimiento.">
    <meta name="theme-color" content="#0c0a09">

    <title>Helpdesk · Confipetrol</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:300,400,500,600,700|ibm-plex-sans:400,500,600|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /*
         * Hallmark · macrostructure: Workbench · tone: funcional-interno · anchor hue: amber
         * pre-emit critique: P4 H5 E4 S5 R5 V4
         * genre: editorial · nav: N9 edge-aligned · footer: Ft1 minimal
         * designed-as-app: helpdesk-confipetrol
         */

        :root {
            --color-accent:         oklch(62% 0.15 60);
            --color-accent-deep:    oklch(50% 0.14 55);
            --color-accent-glow:    oklch(62% 0.15 60 / 0.15);
            --color-accent-ink:     oklch(15% 0.03 50);
            --color-accent-surface: oklch(98% 0.012 60);
            --color-accent-border:  oklch(88% 0.04 60);
            --color-emerald:        oklch(55% 0.14 158);
            --color-emerald-surface:oklch(97% 0.018 155);
            --color-sky:            oklch(52% 0.13 232);
            --color-sky-surface:    oklch(96% 0.02 232);
            --color-rule:           oklch(88% 0.005 60);
            --color-rule-dark:      oklch(22% 0.006 60);
            --color-paper:          oklch(98.5% 0.003 60);
            --color-paper-mid:      oklch(96% 0.005 60);
            --color-paper-dark:     oklch(10% 0.006 60);
            --color-ink:            oklch(16% 0.01 60);
            --color-ink-2:          oklch(40% 0.008 60);
            --color-ink-3:          oklch(58% 0.006 60);

            --font-display: 'Fraunces', Georgia, serif;
            --font-body:    'IBM Plex Sans', system-ui, sans-serif;
            --font-mono:    'IBM Plex Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;

            --ease-out:     cubic-bezier(0.16, 1, 0.3, 1);
            --ease-in:      cubic-bezier(0.4, 0, 1, 1);
            --dur-base:     250ms;
            --dur-fast:     160ms;

            --space-2xs: 0.375rem;
            --space-xs:  0.75rem;
            --space-sm:  1rem;
            --space-md:  1.5rem;
            --space-lg:  2.5rem;
            --space-xl:  4rem;
            --space-2xl: 6rem;

            --radius-sm:   4px;
            --radius-md:   8px;
            --radius-lg:   12px;
        }

        html, body { overflow-x: clip; }

        body {
            font-family: var(--font-body);
            font-feature-settings: 'ss01', 'cv11';
            background-color: var(--color-paper);
            color: var(--color-ink);
        }

        .dark body {
            background-color: var(--color-paper-dark);
            color: oklch(92% 0.005 60);
        }

        .font-display {
            font-family: var(--font-display);
            font-optical-sizing: auto;
            letter-spacing: -0.025em;
        }

        .font-mono { font-family: var(--font-mono); }

        /* ── Barra de cuadrícula técnica — fondo sutil ─────────────── */
        .grid-field {
            background-image:
                linear-gradient(oklch(70% 0.003 60 / 0.08) 1px, transparent 1px),
                linear-gradient(to right, oklch(70% 0.003 60 / 0.08) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .dark .grid-field {
            background-image:
                linear-gradient(oklch(100% 0 0 / 0.035) 1px, transparent 1px),
                linear-gradient(to right, oklch(100% 0 0 / 0.035) 1px, transparent 1px);
        }

        /* ── Botón primario ────────────────────────────────────────── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--color-accent);
            color: var(--color-accent-ink);
            border-radius: var(--radius-md);
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            transition:
                background var(--dur-fast) var(--ease-out),
                box-shadow var(--dur-fast) var(--ease-out);
            box-shadow: 0 1px 3px oklch(62% 0.15 60 / 0.25);
        }
        .btn-primary:hover {
            background: var(--color-accent-deep);
            box-shadow: 0 3px 10px oklch(62% 0.15 60 / 0.3);
        }
        .btn-primary:focus-visible {
            outline: 2px solid var(--color-accent);
            outline-offset: 3px;
        }
        .btn-primary:active { transform: translateY(1px); }

        /* ── Botón fantasma ────────────────────────────────────────── */
        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--color-rule);
            border-radius: var(--radius-md);
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            transition: border-color var(--dur-fast) var(--ease-out), background var(--dur-fast) var(--ease-out);
        }
        .btn-ghost:hover { border-color: var(--color-ink-3); background: oklch(95% 0.003 60 / 0.5); }
        .btn-ghost:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 3px; }
        .dark .btn-ghost { border-color: var(--color-rule-dark); color: oklch(85% 0.005 60); }
        .dark .btn-ghost:hover { border-color: oklch(45% 0.005 60); background: oklch(18% 0.005 60 / 0.6); }

        /* ── Status dot pulsante ───────────────────────────────────── */
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.35; }
        }
        .dot-live {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background: var(--color-emerald);
            animation: pulse-dot 2.2s ease-in-out infinite;
            flex-shrink: 0;
        }

        /* ── Tarjeta módulo ────────────────────────────────────────── */
        .module-card {
            position: relative;
            border: 1px solid var(--color-rule);
            border-radius: var(--radius-lg);
            background: white;
            padding: var(--space-md) var(--space-md) var(--space-lg);
            transition: border-color var(--dur-base) var(--ease-out), box-shadow var(--dur-base) var(--ease-out);
            overflow: hidden;
        }
        .module-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: var(--module-stripe, transparent);
            transition: opacity var(--dur-base) var(--ease-out);
            opacity: 0;
        }
        .module-card:hover { border-color: oklch(75% 0.01 60); box-shadow: 0 4px 16px oklch(40% 0.005 60 / 0.08); }
        .module-card:hover::after { opacity: 1; }
        .dark .module-card { background: oklch(14% 0.007 60); border-color: var(--color-rule-dark); }
        .dark .module-card:hover { border-color: oklch(35% 0.007 60); box-shadow: 0 4px 20px oklch(0% 0 0 / 0.3); }

        /* ── Track de audiencia ────────────────────────────────────── */
        .audience-row {
            display: grid;
            grid-template-columns: 140px 1fr auto;
            align-items: start;
            gap: var(--space-md);
            padding: var(--space-md) 0;
            border-top: 1px solid var(--color-rule);
        }
        .dark .audience-row { border-color: var(--color-rule-dark); }
        @media (max-width: 767px) {
            .audience-row { grid-template-columns: 1fr; }
        }

        /* ── Ficha de ticket (hero) ────────────────────────────────── */
        .ticket-card {
            border: 1px solid var(--color-rule);
            border-radius: var(--radius-lg);
            background: white;
            overflow: hidden;
            box-shadow: 0 8px 32px oklch(20% 0.005 60 / 0.12), 0 2px 8px oklch(20% 0.005 60 / 0.06);
        }
        .dark .ticket-card {
            background: oklch(13% 0.007 60);
            border-color: var(--color-rule-dark);
            box-shadow: 0 8px 32px oklch(0% 0 0 / 0.45);
        }
        .ticket-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid var(--color-rule);
            background: var(--color-paper-mid);
        }
        .dark .ticket-header {
            background: oklch(15% 0.007 60);
            border-color: var(--color-rule-dark);
        }

        /* ── Animaciones de entrada ─────────────────────────────────── */
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .enter { animation: slide-up 0.55s var(--ease-out) both; }
        .enter-1 { animation-delay: 0.04s; }
        .enter-2 { animation-delay: 0.12s; }
        .enter-3 { animation-delay: 0.22s; }
        .enter-4 { animation-delay: 0.34s; }
        .enter-5 { animation-delay: 0.48s; }

        @media (prefers-reduced-motion: reduce) {
            .enter, .dot-live { animation: none !important; opacity: 1; }
        }
    </style>
</head>
<body class="antialiased selection:bg-amber-200 selection:text-stone-900 dark:selection:bg-amber-900 dark:selection:text-amber-100">

{{-- ═══════════════════════════════════════════
     NAV — N9 Edge-aligned
     ═══════════════════════════════════════════ --}}
<header class="fixed top-0 inset-x-0 z-50 bg-white/85 dark:bg-stone-950/85 backdrop-blur-md border-b border-stone-200/70 dark:border-stone-800/70">
    <div class="mx-auto max-w-7xl px-6 lg:px-10 h-14 flex items-center justify-between">

        {{-- Wordmark --}}
        <a href="/" class="flex items-center gap-3 group" aria-label="Helpdesk Confipetrol — Inicio">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="" aria-hidden="true"
                 class="h-6 w-auto dark:hidden">
            <img src="{{ asset('images/logo-confipetrol.png') }}" alt="" aria-hidden="true"
                 class="h-6 w-auto hidden dark:block">
            <span class="hidden sm:flex items-center gap-2.5 pl-3 ml-0.5 border-l border-stone-200 dark:border-stone-800">
                <span class="font-mono text-[10px] font-medium tracking-[0.2em] uppercase text-stone-500 dark:text-stone-400">Helpdesk</span>
                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400">v1.9</span>
            </span>
        </a>

        {{-- Acceso único --}}
        <div class="flex items-center gap-3">
            <span class="hidden sm:flex items-center gap-2 font-mono text-[10px] tracking-widest text-emerald-600 dark:text-emerald-500">
                <span class="dot-live"></span>
                Operativo
            </span>
            <span class="hidden sm:block w-px h-4 bg-stone-200 dark:bg-stone-800"></span>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary">
                    Ir al panel
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-primary">
                    Iniciar sesión
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @endauth
        </div>
    </div>
</header>

<main class="pt-14">

    {{-- ═══════════════════════════════════════════
         HERO — Workbench: orientar, no convencer
         Layout: texto izquierda + ficha operativa derecha
         ═══════════════════════════════════════════ --}}
    <section class="relative grid-field bg-stone-50 dark:bg-stone-950 border-b border-stone-200 dark:border-stone-800">

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10 pt-16 pb-20 md:pt-20 md:pb-28">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-start">

                {{-- Columna de texto — directa, sin ornamentos de ventas --}}
                <div class="lg:max-w-lg">
                    <div class="enter enter-1 flex items-center gap-2.5 mb-8">
                        <div class="h-px w-10 bg-amber-400 dark:bg-amber-600"></div>
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-600 dark:text-amber-500">Plataforma interna · Confipetrol</span>
                    </div>

                    <h1 class="enter enter-2 font-display text-[clamp(2.5rem,5.5vw,4.25rem)] font-light leading-[1.02] tracking-tight text-stone-900 dark:text-stone-50" style="overflow-wrap: anywhere; min-width: 0;">
                        Tu soporte.
                        <br>
                        <span class="font-semibold" style="color: var(--color-accent); text-decoration: underline; text-decoration-color: oklch(62% 0.15 60 / 0.3); text-underline-offset: 5px;">Todo en un lugar.</span>
                    </h1>

                    <p class="enter enter-3 mt-6 text-base sm:text-lg text-stone-600 dark:text-stone-300 leading-relaxed" style="max-width: 38ch;">
                        Tickets con SLA, base de conocimiento con IA, inventario de equipos
                        y control de acceso por rol. Sin hojas de cálculo, sin correos perdidos.
                    </p>

                    <div class="enter enter-4 mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary">
                                Entrar al panel
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary">
                                Iniciar sesión
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                            @if(config('services.azure.client_id'))
                                <a href="{{ route('auth.azure') }}" class="btn-ghost">
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 23 23" fill="currentColor" aria-hidden="true"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                                    Entrar con Azure
                                </a>
                            @endif
                        @endauth
                    </div>

                    {{-- Tres stats de contexto rápido — datos reales, sin inventar métricas --}}
                    <div class="enter enter-5 mt-10 pt-8 border-t border-stone-200 dark:border-stone-800 grid grid-cols-3 gap-4">
                        <div>
                            <div class="font-mono text-xs tracking-widest text-stone-400 dark:text-stone-500 mb-0.5">Módulos</div>
                            <div class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">6</div>
                        </div>
                        <div>
                            <div class="font-mono text-xs tracking-widest text-stone-400 dark:text-stone-500 mb-0.5">Paneles</div>
                            <div class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">3</div>
                        </div>
                        <div>
                            <div class="font-mono text-xs tracking-widest text-stone-400 dark:text-stone-500 mb-0.5">Roles</div>
                            <div class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">7</div>
                        </div>
                    </div>
                </div>

                {{-- Columna derecha: ficha de ticket funcional --}}
                <div class="lg:mt-4">
                    <div class="ticket-card">
                        {{-- Header funcional --}}
                        <div class="ticket-header">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-stone-400 dark:text-stone-500">Helpdesk</span>
                                <span class="text-stone-300 dark:text-stone-700">/</span>
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-stone-400 dark:text-stone-500">Soporte</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="dot-live"></span>
                                <span class="font-mono text-[10px] tracking-widest text-emerald-600 dark:text-emerald-500">En vivo</span>
                            </div>
                        </div>

                        {{-- Ticket activo --}}
                        <div class="p-5">
                            {{-- ID + status --}}
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <div class="font-mono text-[10px] tracking-[0.18em] uppercase text-stone-400 dark:text-stone-500 mb-1">#TK-2481</div>
                                    <p class="font-display text-lg font-medium leading-snug text-stone-900 dark:text-stone-50">
                                        No conecta al wifi corporativo
                                    </p>
                                </div>
                                <span class="shrink-0 inline-flex items-center gap-1.5 rounded px-2 py-1 font-mono text-[10px] font-medium" style="background: var(--color-accent-surface); color: oklch(48% 0.13 55); border: 1px solid var(--color-accent-border);">
                                    <span style="display:inline-block; width:5px; height:5px; border-radius:9999px; background:var(--color-accent); flex-shrink:0;"></span>
                                    En progreso
                                </span>
                            </div>

                            {{-- Metadatos --}}
                            <div class="grid grid-cols-2 gap-y-3 gap-x-6 py-3.5 border-y border-stone-100 dark:border-stone-800/60 mb-4">
                                <div>
                                    <div class="font-mono text-[9px] tracking-widest uppercase text-stone-400 dark:text-stone-500 mb-0.5">Solicitante</div>
                                    <div class="text-sm text-stone-700 dark:text-stone-300">M. Ramírez · TI</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[9px] tracking-widest uppercase text-stone-400 dark:text-stone-500 mb-0.5">SLA restante</div>
                                    <div class="font-mono text-sm text-orange-600 dark:text-orange-400">2h 14m</div>
                                </div>
                                <div>
                                    <div class="font-mono text-[9px] tracking-widest uppercase text-stone-400 dark:text-stone-500 mb-0.5">Prioridad</div>
                                    <div class="flex items-center gap-1.5 text-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>
                                        <span class="text-stone-700 dark:text-stone-300">Alta</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="font-mono text-[9px] tracking-widest uppercase text-stone-400 dark:text-stone-500 mb-0.5">Categoría</div>
                                    <div class="text-sm text-stone-700 dark:text-stone-300">Conectividad</div>
                                </div>
                            </div>

                            {{-- Última respuesta del agente --}}
                            <div class="flex gap-3">
                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-semibold text-white" style="background: var(--color-emerald);">A</div>
                                <div class="min-w-0">
                                    <div class="flex items-baseline gap-2 text-xs mb-0.5">
                                        <span class="font-medium text-stone-700 dark:text-stone-300">Agente TI</span>
                                        <span class="font-mono text-stone-400">·</span>
                                        <span class="font-mono text-stone-400">primera respuesta</span>
                                    </div>
                                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
                                        Revisando el punto de acceso del sector. Te informo en breve.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Anotación tipo especificación técnica --}}
                    <div class="mt-3 flex items-center gap-2 font-mono text-[10px] tracking-[0.18em] text-stone-400 dark:text-stone-500">
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Vista de panel de agente · actualización cada 5 min
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         MÓDULOS — Grid 2×2 funcional
         Sin numeración ornamental, sin efectos de revelación
         ═══════════════════════════════════════════ --}}
    <section id="sistema" class="py-20 lg:py-28 bg-white dark:bg-stone-950">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">

            <div class="mb-12 lg:mb-16 flex flex-col sm:flex-row sm:items-end justify-between gap-6">
                <div>
                    <h2 class="font-display text-3xl lg:text-4xl font-light leading-tight text-stone-900 dark:text-stone-50" style="overflow-wrap: anywhere; min-width: 0;">
                        Una sola consola,<br>
                        <span class="font-semibold" style="color: var(--color-accent);">cuatro capacidades.</span>
                    </h2>
                </div>
                <p class="sm:max-w-xs text-sm text-stone-500 dark:text-stone-400 leading-relaxed sm:text-right">
                    Todo lo que los equipos de TI, RRHH, Compras y Operaciones necesitan, integrado y con trazabilidad.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Tickets & SLA --}}
                <div class="module-card" style="--module-stripe: var(--color-accent);">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center justify-center w-9 h-9 rounded-md" style="background: var(--color-accent-surface);">
                            <svg class="w-5 h-5" style="color: var(--color-accent);" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <span class="font-mono text-[9px] tracking-[0.2em] uppercase text-stone-400 dark:text-stone-500">Módulo 1</span>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-2">Tickets &amp; SLA</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
                        Prioridad ITIL automática (impacto × urgencia). SLA por departamento, monitoreo cada 5 min y escalación a 70 / 90 / 100%. Flujo completo: Nuevo → Asignado → En progreso → Resuelto → Cerrado.
                    </p>
                </div>

                {{-- Conocimiento & IA --}}
                <div class="module-card" style="--module-stripe: var(--color-emerald);">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center justify-center w-9 h-9 rounded-md" style="background: var(--color-emerald-surface);">
                            <svg class="w-5 h-5" style="color: var(--color-emerald);" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <span class="font-mono text-[9px] tracking-[0.2em] uppercase text-stone-400 dark:text-stone-500">Módulo 2</span>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-2">Conocimiento &amp; IA</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
                        Base de artículos por departamento con flujo de aprobación. Asistente IA con RAG sobre el contenido publicado. Redacción en lenguaje natural, estructura en Markdown.
                    </p>
                </div>

                {{-- Inventario --}}
                <div class="module-card" style="--module-stripe: var(--color-sky);">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center justify-center w-9 h-9 rounded-md" style="background: var(--color-sky-surface);">
                            <svg class="w-5 h-5" style="color: var(--color-sky);" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="font-mono text-[9px] tracking-[0.2em] uppercase text-stone-400 dark:text-stone-500">Módulo 3</span>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-2">Inventario de equipos</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
                        Doble captura: web-scan automático + agente PowerShell desplegable con un comando. Hardware, software, BIOS y red por equipo. Asignación digital de activos con acta firmada.
                    </p>
                </div>

                {{-- Control de acceso --}}
                <div class="module-card" style="--module-stripe: oklch(50% 0.005 60);">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div class="flex items-center justify-center w-9 h-9 rounded-md bg-stone-100 dark:bg-stone-800">
                            <svg class="w-5 h-5 text-stone-600 dark:text-stone-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <span class="font-mono text-[9px] tracking-[0.2em] uppercase text-stone-400 dark:text-stone-500">Módulo 4</span>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-2">Control de acceso</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed">
                        7 roles con scope por departamento. Fortify + 2FA opcional y SSO Azure AD. Cada agente ve solo lo que le compete; el supervisor su área; el admin todo.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         AUDIENCIAS — tres paneles, diseño horizontal
         No como fichas de venta, sino como mapa del sistema
         ═══════════════════════════════════════════ --}}
    <section id="paneles" class="py-20 lg:py-28 bg-stone-50 dark:bg-stone-950 border-t border-stone-200 dark:border-stone-800">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">

            <div class="mb-12">
                <h2 class="font-display text-3xl lg:text-4xl font-light text-stone-900 dark:text-stone-50" style="overflow-wrap: anywhere; min-width: 0;">
                    Tres paneles.<br>
                    <span class="font-medium text-stone-500 dark:text-stone-400">El mismo motor.</span>
                </h2>
            </div>

            {{-- Fila: Usuario final --}}
            <div class="audience-row">
                <div>
                    <div class="font-mono text-[10px] tracking-[0.2em] uppercase mb-1" style="color: var(--color-emerald);">Usuario final</div>
                    <div class="font-mono text-[10px] tracking-widest text-stone-400">/portal</div>
                </div>
                <div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-1">Portal del solicitante</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed mb-3">
                        Crea solicitudes, sigue el progreso en tiempo real, consulta el centro de ayuda y gestiona los activos asignados.
                    </p>
                    <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-stone-500 dark:text-stone-400">
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-emerald);"></span>Mis tickets y su estado</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-emerald);"></span>Centro de ayuda + IA</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-emerald);"></span>Mis equipos asignados</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-emerald);"></span>Encuesta de satisfacción</li>
                    </ul>
                </div>
                <div class="hidden md:block text-right">
                    <span class="font-mono text-[10px] tracking-widest text-stone-400 dark:text-stone-500">Empleados</span>
                </div>
            </div>

            {{-- Fila: Soporte --}}
            <div class="audience-row">
                <div>
                    <div class="font-mono text-[10px] tracking-[0.2em] uppercase mb-1" style="color: var(--color-sky);">Soporte</div>
                    <div class="font-mono text-[10px] tracking-widest text-stone-400">/soporte</div>
                </div>
                <div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-1">Panel de agentes</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed mb-3">
                        Gestión de tickets con todo el contexto: SLA visible, historial completo, plantillas reutilizables y traslado entre áreas en un clic.
                    </p>
                    <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-stone-500 dark:text-stone-400">
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-sky);"></span>Cola de tickets con SLA</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-sky);"></span>Plantillas predefinidas</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-sky);"></span>Traslado y escalación</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="background: var(--color-sky);"></span>Audit log de prioridad</li>
                    </ul>
                </div>
                <div class="hidden md:block text-right">
                    <span class="font-mono text-[10px] tracking-widest text-stone-400 dark:text-stone-500">Agentes TI / RRHH</span>
                </div>
            </div>

            {{-- Fila: Admin --}}
            <div class="audience-row" style="border-bottom: 1px solid var(--color-rule);">
                <div>
                    <div class="font-mono text-[10px] tracking-[0.2em] uppercase mb-1" style="color: var(--color-accent);">Admin</div>
                    <div class="font-mono text-[10px] tracking-widest text-stone-400">/admin</div>
                </div>
                <div>
                    <h3 class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mb-1">Panel global</h3>
                    <p class="text-sm text-stone-600 dark:text-stone-400 leading-relaxed mb-3">
                        Configuración total del sistema, usuarios, departamentos, SLA, inventario y reportes ejecutivos para gerencia.
                    </p>
                    <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-stone-500 dark:text-stone-400">
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="color: var(--color-accent); background: var(--color-accent);"></span>Reporte SLA global</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="color: var(--color-accent); background: var(--color-accent);"></span>Config. departamentos</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="color: var(--color-accent); background: var(--color-accent);"></span>Inventario + tokens API</li>
                        <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full flex-shrink-0" style="color: var(--color-accent); background: var(--color-accent);"></span>Respaldos automáticos</li>
                    </ul>
                </div>
                <div class="hidden md:block text-right">
                    <span class="font-mono text-[10px] tracking-widest text-stone-400 dark:text-stone-500">Super admin</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         CTA FINAL — Acceso
         Layout: barra horizontal, no sección centrada
         ═══════════════════════════════════════════ --}}
    <section class="bg-stone-950 dark:bg-stone-950 border-t border-stone-800">
        <div class="mx-auto max-w-7xl px-6 lg:px-10 py-14 lg:py-20">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-8">
                <div>
                    <h2 class="font-display text-2xl sm:text-3xl font-light text-stone-50 mb-2" style="overflow-wrap: anywhere; min-width: 0;">
                        Accede con tu cuenta corporativa.
                    </h2>
                    <p class="text-sm text-stone-400 leading-relaxed" style="max-width: 40ch;">
                        Si trabajas en Confipetrol, ya tienes acceso. Solo inicia sesión con tu correo institucional o por Azure AD.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-md px-7 py-3.5 text-sm font-semibold whitespace-nowrap transition"
                           style="background: var(--color-accent); color: var(--color-accent-ink);">
                            Ir al panel
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-md px-7 py-3.5 text-sm font-semibold whitespace-nowrap transition"
                           style="background: var(--color-accent); color: var(--color-accent-ink);"
                           onmouseover="this.style.background='oklch(50% 0.14 55)'"
                           onmouseout="this.style.background='var(--color-accent)'"
                           onfocus="this.style.outline='2px solid var(--color-accent)'"
                           onfocusout="this.style.outline='none'">
                            Iniciar sesión
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        @if(config('services.azure.client_id'))
                            <a href="{{ route('auth.azure') }}"
                               class="inline-flex items-center justify-center gap-2 rounded-md border border-stone-700 bg-stone-900 hover:bg-stone-800 hover:border-stone-600 px-7 py-3.5 text-sm font-medium text-stone-200 whitespace-nowrap transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-3"
                               style="focus-visible:outline-color: var(--color-accent);">
                                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 23 23" fill="currentColor" aria-hidden="true"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                                Azure AD · SSO
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         FOOTER — Ft1 Minimal
         ═══════════════════════════════════════════ --}}
    <footer class="bg-stone-950 border-t border-stone-800/60">
        <div class="mx-auto max-w-7xl px-6 lg:px-10 py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">

            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="h-5 w-auto opacity-60">
                <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-stone-600">
                    Helpdesk · Plataforma interna · &copy; {{ date('Y') }}
                </span>
            </div>

            <div class="flex items-center gap-5 font-mono text-[10px] tracking-[0.18em] uppercase text-stone-600">
                <a href="#sistema" class="hover:text-stone-400 transition">Sistema</a>
                <a href="#paneles" class="hover:text-stone-400 transition">Paneles</a>
                <a href="{{ route('login') }}" class="hover:text-stone-400 transition">Acceder</a>
                <span class="flex items-center gap-1.5 text-stone-700">
                    <span class="dot-live" style="background: var(--color-emerald); opacity: 0.6;"></span>
                    Laravel {{ Illuminate\Foundation\Application::VERSION }}
                </span>
            </div>
        </div>
    </footer>

</main>

</body>
</html>
