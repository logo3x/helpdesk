<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Helpdesk Confipetrol — plataforma interna de soporte. Tickets, base de conocimiento e inventario en una sola consola.">
    <meta name="theme-color" content="#0c0a09">

    <title>Helpdesk Confipetrol · Centro de operaciones de soporte</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{-- Tipografía: Fraunces (display serif con carácter editorial),
         IBM Plex Sans (body humanista-técnico), IBM Plex Mono (datos). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:300,400,500,600,700|ibm-plex-sans:400,500,600|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --confi-amber: #d97706;
            --confi-amber-bright: #f59e0b;
            --confi-emerald: #059669;
            --confi-sky: #0284c7;
        }

        body {
            font-family: 'IBM Plex Sans', system-ui, sans-serif;
            font-feature-settings: 'ss01', 'cv11';
        }

        .font-display {
            font-family: 'Fraunces', Georgia, serif;
            font-feature-settings: 'ss01';
            font-optical-sizing: auto;
            letter-spacing: -0.02em;
        }

        .font-mono {
            font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        }

        /* Grid blueprint sutil de fondo */
        .blueprint-grid {
            background-image:
                linear-gradient(to right, rgba(120,113,108,0.07) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(120,113,108,0.07) 1px, transparent 1px);
            background-size: 56px 56px;
        }
        .dark .blueprint-grid {
            background-image:
                linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px);
        }

        /* Tick marks horizontal — referencia visual al SLA timer */
        .tick-rule {
            background-image: linear-gradient(
                to right,
                currentColor 0,
                currentColor 1px,
                transparent 1px,
                transparent 12px
            );
            background-size: 12px 100%;
            background-repeat: repeat-x;
            background-position: 0 50%;
            height: 8px;
        }

        /* Animaciones */
        @keyframes rise {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes glow-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        @keyframes scan-line {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100%); }
        }
        .rise { animation: rise 0.7s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .rise-1 { animation-delay: 0.05s; }
        .rise-2 { animation-delay: 0.15s; }
        .rise-3 { animation-delay: 0.25s; }
        .rise-4 { animation-delay: 0.35s; }
        .rise-5 { animation-delay: 0.45s; }
        .rise-6 { animation-delay: 0.6s; }
        .glow-pulse { animation: glow-pulse 2.4s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .rise, .glow-pulse { animation: none !important; }
        }

        /* Estilo NOC: status indicator con halo */
        .status-dot {
            position: relative;
        }
        .status-dot::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 9999px;
            background: currentColor;
            opacity: 0.2;
            animation: glow-pulse 2.4s ease-in-out infinite;
        }

        /* Hover preciso sobre cards (acento lateral) */
        .track-card {
            position: relative;
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s;
        }
        .track-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 24px;
            bottom: 24px;
            width: 2px;
            background: var(--track-accent, transparent);
            transition: background 0.3s;
        }
        .track-card:hover {
            transform: translateY(-4px);
        }
        .track-card:hover::before {
            background: var(--track-accent);
        }

        /* Botones bordes definidos */
        .btn-primary {
            background: linear-gradient(135deg, #d97706 0%, #ea580c 100%);
            box-shadow: 0 1px 0 0 rgba(255,255,255,0.2) inset, 0 8px 20px -8px rgba(217,119,6,0.5);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #c2410c 0%, #d97706 100%);
        }
    </style>
</head>
<body class="antialiased text-stone-800 bg-stone-50 dark:bg-stone-950 dark:text-stone-100 selection:bg-amber-300 selection:text-stone-900">

{{-- ╔════════════════════════════════════════════════════════════════════════╗
     │  TOP BAR — fija, marca + acceso                                        │
     ╚════════════════════════════════════════════════════════════════════════╝ --}}
<header class="fixed top-0 inset-x-0 z-50 backdrop-blur-md bg-stone-50/80 dark:bg-stone-950/80 border-b border-stone-200/60 dark:border-stone-800/60">
    <nav class="mx-auto max-w-7xl px-6 lg:px-10 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3 group">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol"
                 class="h-7 w-auto dark:hidden">
            <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol"
                 class="h-7 w-auto hidden dark:block">
            <span class="hidden sm:flex items-center gap-2 pl-3 ml-1 border-l border-stone-300 dark:border-stone-700">
                <span class="font-mono text-[11px] font-medium tracking-[0.18em] uppercase text-stone-500 dark:text-stone-400">Helpdesk</span>
                <span class="font-mono text-[10px] text-amber-600 dark:text-amber-500">v1.9</span>
            </span>
        </a>

        <div class="flex items-center gap-3">
            <a href="#sistema" class="hidden md:inline font-mono text-xs tracking-widest uppercase text-stone-500 hover:text-amber-600 dark:text-stone-400 dark:hover:text-amber-500 transition">
                Sistema
            </a>
            <a href="#tracks" class="hidden md:inline font-mono text-xs tracking-widest uppercase text-stone-500 hover:text-amber-600 dark:text-stone-400 dark:hover:text-amber-500 transition">
                Audiencias
            </a>
            <span class="hidden md:inline-block w-px h-4 bg-stone-300 dark:bg-stone-700 mx-1"></span>

            @auth
                <a href="{{ route('dashboard') }}"
                   class="btn-primary inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white">
                    Entrar al panel
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="btn-primary inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white">
                    Iniciar sesión
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @endauth
        </div>
    </nav>
</header>

<main class="pt-16">

    {{-- ╔════════════════════════════════════════════════════════════════════╗
         │  HERO — split asimétrico, blueprint sutil, mock ticket            │
         ╚════════════════════════════════════════════════════════════════════╝ --}}
    <section class="relative blueprint-grid overflow-hidden">
        {{-- Halo amber atrás del hero --}}
        <div class="absolute -top-40 right-1/4 w-[480px] h-[480px] rounded-full bg-amber-400/20 blur-[120px] pointer-events-none"></div>
        <div class="absolute top-1/2 -left-40 w-[400px] h-[400px] rounded-full bg-emerald-400/10 blur-[120px] pointer-events-none dark:bg-emerald-500/15"></div>

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10 pt-20 pb-32 md:pt-24 md:pb-40">

            {{-- Overline: status + timestamp NOC --}}
            <div class="flex items-center gap-3 rise rise-1">
                <span class="status-dot inline-block w-2 h-2 rounded-full bg-emerald-500 text-emerald-500"></span>
                <span class="font-mono text-[11px] font-medium tracking-[0.22em] uppercase text-emerald-700 dark:text-emerald-400">
                    Sistema operativo
                </span>
                <span class="text-stone-300 dark:text-stone-700">·</span>
                <span class="font-mono text-[11px] tracking-[0.18em] text-stone-500 dark:text-stone-500">
                    {{ now()->translatedFormat('d M Y · H:i') }}
                </span>
                <span class="hidden sm:inline text-stone-300 dark:text-stone-700">·</span>
                <span class="hidden sm:inline font-mono text-[11px] tracking-[0.18em] text-stone-500 dark:text-stone-500">
                    Confipetrol · CO
                </span>
            </div>

            <div class="mt-12 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">

                {{-- ── Columna izquierda: Headline editorial ─────────────── --}}
                <div class="lg:col-span-7">
                    <h1 class="rise rise-2 font-display text-[clamp(2.75rem,7vw,5.5rem)] font-light leading-[0.98] tracking-tight text-stone-900 dark:text-stone-50">
                        Centro de
                        <span class="italic font-medium text-amber-600 dark:text-amber-500">operaciones</span>
                        <br class="hidden sm:block">
                        de soporte
                        <span class="italic font-medium text-stone-500 dark:text-stone-400">interno</span>.
                    </h1>

                    <div class="rise rise-3 mt-8 max-w-xl">
                        <div class="text-amber-600 dark:text-amber-700 tick-rule mb-6"></div>
                        <p class="text-base sm:text-lg text-stone-600 dark:text-stone-300 leading-relaxed">
                            Tickets con SLA monitoreado, base de conocimiento con asistente IA,
                            inventario de equipos y workflow por departamento. Construido a medida
                            para los equipos de TI, RRHH, Compras y Operaciones de Confipetrol.
                        </p>
                    </div>

                    <div class="rise rise-4 mt-10 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="btn-primary inline-flex items-center justify-center gap-2 rounded-md px-7 py-3.5 text-sm font-semibold text-white">
                                Ir al panel
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="btn-primary inline-flex items-center justify-center gap-2 rounded-md px-7 py-3.5 text-sm font-semibold text-white">
                                Iniciar sesión
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @endauth

                        <a href="#sistema"
                           class="inline-flex items-center justify-center gap-2 rounded-md border border-stone-300 dark:border-stone-700 bg-white/50 dark:bg-stone-900/50 backdrop-blur px-7 py-3.5 text-sm font-medium text-stone-700 dark:text-stone-200 hover:bg-stone-100 dark:hover:bg-stone-800 transition">
                            Ver capacidades
                            <span class="font-mono text-[11px] text-stone-400">↓</span>
                        </a>
                    </div>
                </div>

                {{-- ── Columna derecha: Mock ticket flotante ──────────────── --}}
                <div class="lg:col-span-5 rise rise-5">
                    <div class="relative">
                        {{-- Etiqueta de "captura simulada" --}}
                        <div class="absolute -top-7 right-0 flex items-center gap-2 font-mono text-[10px] tracking-[0.18em] uppercase text-stone-400 dark:text-stone-500">
                            <span class="w-3 h-px bg-stone-400 dark:bg-stone-600"></span>
                            Vista de ticket — /soporte
                        </div>

                        <div class="rounded-lg border border-stone-200 dark:border-stone-800 bg-white dark:bg-stone-900 shadow-2xl shadow-stone-900/10 dark:shadow-black/40 overflow-hidden">
                            {{-- Barra del navegador estilizada --}}
                            <div class="flex items-center gap-2 px-4 py-3 border-b border-stone-200 dark:border-stone-800 bg-stone-50 dark:bg-stone-950/50">
                                <div class="flex gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-stone-300 dark:bg-stone-700"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-stone-300 dark:bg-stone-700"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-stone-300 dark:bg-stone-700"></span>
                                </div>
                                <div class="flex-1 mx-2 px-2 py-1 rounded bg-white dark:bg-stone-900 border border-stone-200 dark:border-stone-800">
                                    <span class="font-mono text-[10px] text-stone-500 dark:text-stone-400">helpdesk.confipetrol.com / soporte / tickets</span>
                                </div>
                            </div>

                            {{-- Contenido del ticket --}}
                            <div class="p-5 space-y-4">
                                {{-- Header del ticket --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-mono text-[10px] tracking-[0.18em] uppercase text-stone-400">Ticket</div>
                                        <div class="font-display text-xl font-medium text-stone-900 dark:text-stone-50 mt-0.5">No conecta al wifi corporativo</div>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded px-2 py-0.5 text-[11px] font-mono font-medium bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:ring-amber-900">
                                        <span class="w-1 h-1 rounded-full bg-amber-500 glow-pulse"></span>
                                        En progreso
                                    </span>
                                </div>

                                {{-- Meta grid --}}
                                <div class="grid grid-cols-2 gap-y-2.5 gap-x-4 pt-3 border-t border-stone-100 dark:border-stone-800/60">
                                    <div>
                                        <div class="font-mono text-[10px] tracking-widest uppercase text-stone-400 mb-0.5">Solicitante</div>
                                        <div class="text-sm text-stone-800 dark:text-stone-200">M. Ramírez · TI</div>
                                    </div>
                                    <div>
                                        <div class="font-mono text-[10px] tracking-widest uppercase text-stone-400 mb-0.5">Asignado</div>
                                        <div class="text-sm text-stone-800 dark:text-stone-200">Agente · TI</div>
                                    </div>
                                    <div>
                                        <div class="font-mono text-[10px] tracking-widest uppercase text-stone-400 mb-0.5">Prioridad</div>
                                        <div class="inline-flex items-center gap-1.5 text-sm">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                                            <span class="text-stone-800 dark:text-stone-200">Alta</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-mono text-[10px] tracking-widest uppercase text-stone-400 mb-0.5">Categoría</div>
                                        <div class="text-sm text-stone-800 dark:text-stone-200">Conectividad</div>
                                    </div>
                                </div>

                                {{-- Última actividad --}}
                                <div class="flex items-start gap-2.5 pt-4 border-t border-stone-100 dark:border-stone-800/60">
                                    <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs font-semibold">A</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-baseline gap-2 text-xs">
                                            <span class="font-medium text-stone-700 dark:text-stone-300">Agente TI</span>
                                            <span class="font-mono text-stone-400">·</span>
                                            <span class="font-mono text-stone-400">primera respuesta</span>
                                        </div>
                                        <p class="mt-0.5 text-sm text-stone-700 dark:text-stone-300">
                                            Hola, he tomado tu ticket y lo voy a revisar. Te contacto en breve con novedades.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Anotación lateral tipo blueprint --}}
                        <div class="hidden lg:flex absolute -left-32 top-12 items-center gap-2 -rotate-90 origin-right">
                            <span class="w-12 h-px bg-stone-300 dark:bg-stone-700"></span>
                            <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-stone-400">Tiempo real</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔════════════════════════════════════════════════════════════════════╗
         │  CAPABILITIES — sistema modular                                    │
         ╚════════════════════════════════════════════════════════════════════╝ --}}
    <section id="sistema" class="relative py-24 lg:py-32 bg-stone-50 dark:bg-stone-950">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">

            {{-- Section header --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 mb-16 lg:mb-24">
                <div class="lg:col-span-4">
                    <div class="font-mono text-[11px] tracking-[0.22em] uppercase text-amber-600 dark:text-amber-500 mb-4">
                        <span class="text-stone-400 dark:text-stone-600">— 01</span> Sistema
                    </div>
                    <h2 class="font-display text-4xl lg:text-5xl font-light leading-tight tracking-tight text-stone-900 dark:text-stone-50">
                        Una sola consola.
                        <span class="italic text-stone-500 dark:text-stone-400">Múltiples capacidades.</span>
                    </h2>
                </div>
                <div class="lg:col-span-7 lg:col-start-6">
                    <p class="font-display text-xl lg:text-2xl font-light leading-relaxed text-stone-600 dark:text-stone-300">
                        Helpdesk Confipetrol consolida lo que antes vivía en correos sueltos,
                        hojas de cálculo y herramientas dispersas. Una plataforma integrada
                        donde cada solicitud, cada equipo y cada respuesta tienen trazabilidad.
                    </p>
                </div>
            </div>

            {{-- Capabilities grid: alterna layout --}}
            <div class="space-y-px bg-stone-200 dark:bg-stone-800">
                {{-- Tickets --}}
                <div class="grid grid-cols-1 md:grid-cols-12 bg-stone-50 dark:bg-stone-950 hover:bg-white dark:hover:bg-stone-900 transition">
                    <div class="md:col-span-1 p-6 md:p-8 font-mono text-[11px] tracking-[0.22em] text-stone-400 dark:text-stone-600 md:border-r border-stone-200 dark:border-stone-800">
                        001
                    </div>
                    <div class="md:col-span-4 p-6 md:p-8 md:border-r border-stone-200 dark:border-stone-800">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-500 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <h3 class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">Tickets &amp; SLA</h3>
                    </div>
                    <div class="md:col-span-7 p-6 md:p-8 text-stone-600 dark:text-stone-300 leading-relaxed">
                        Prioridad ITIL automática (impacto × urgencia), SLA por departamento con monitoreo
                        cada 5 minutos y escalación a 70/90/100%. Plantillas reutilizables, traslado entre
                        áreas y workflow Nuevo → Asignado → En progreso → Resuelto → Cerrado.
                    </div>
                </div>

                {{-- KB + IA --}}
                <div class="grid grid-cols-1 md:grid-cols-12 bg-stone-50 dark:bg-stone-950 hover:bg-white dark:hover:bg-stone-900 transition">
                    <div class="md:col-span-1 p-6 md:p-8 font-mono text-[11px] tracking-[0.22em] text-stone-400 dark:text-stone-600 md:border-r border-stone-200 dark:border-stone-800">
                        002
                    </div>
                    <div class="md:col-span-4 p-6 md:p-8 md:border-r border-stone-200 dark:border-stone-800">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-500 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">Conocimiento &amp; IA</h3>
                    </div>
                    <div class="md:col-span-7 p-6 md:p-8 text-stone-600 dark:text-stone-300 leading-relaxed">
                        Base de artículos por departamento con flujo formal de aprobación
                        (agente → supervisor). Asistente IA con RAG sobre el contenido publicado.
                        Redacción de artículos en lenguaje natural — el LLM los estructura en Markdown.
                    </div>
                </div>

                {{-- Inventario --}}
                <div class="grid grid-cols-1 md:grid-cols-12 bg-stone-50 dark:bg-stone-950 hover:bg-white dark:hover:bg-stone-900 transition">
                    <div class="md:col-span-1 p-6 md:p-8 font-mono text-[11px] tracking-[0.22em] text-stone-400 dark:text-stone-600 md:border-r border-stone-200 dark:border-stone-800">
                        003
                    </div>
                    <div class="md:col-span-4 p-6 md:p-8 md:border-r border-stone-200 dark:border-stone-800">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-sky-100 dark:bg-sky-950/50 text-sky-600 dark:text-sky-500 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">Inventario PCs</h3>
                    </div>
                    <div class="md:col-span-7 p-6 md:p-8 text-stone-600 dark:text-stone-300 leading-relaxed">
                        Doble captura: web-scan automático al abrir el portal + agente PowerShell
                        que se despliega con un solo comando. Hardware, software, BIOS y red por equipo.
                        Configurable por departamento — solo IT lo ve por defecto.
                    </div>
                </div>

                {{-- Roles --}}
                <div class="grid grid-cols-1 md:grid-cols-12 bg-stone-50 dark:bg-stone-950 hover:bg-white dark:hover:bg-stone-900 transition">
                    <div class="md:col-span-1 p-6 md:p-8 font-mono text-[11px] tracking-[0.22em] text-stone-400 dark:text-stone-600 md:border-r border-stone-200 dark:border-stone-800">
                        004
                    </div>
                    <div class="md:col-span-4 p-6 md:p-8 md:border-r border-stone-200 dark:border-stone-800">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-md bg-stone-200 dark:bg-stone-800 text-stone-700 dark:text-stone-300 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h3 class="font-display text-2xl font-medium text-stone-900 dark:text-stone-50">Control de acceso</h3>
                    </div>
                    <div class="md:col-span-7 p-6 md:p-8 text-stone-600 dark:text-stone-300 leading-relaxed">
                        7 roles con scope por departamento. Login unificado vía Fortify + 2FA
                        opcional, SSO Azure AD configurable. Cada agente solo ve lo que le
                        compete; el supervisor su depto; el admin todo.
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔════════════════════════════════════════════════════════════════════╗
         │  TRACKS — tres audiencias                                          │
         ╚════════════════════════════════════════════════════════════════════╝ --}}
    <section id="tracks" class="relative py-24 lg:py-32 bg-white dark:bg-stone-900">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 mb-16">
                <div class="lg:col-span-4">
                    <div class="font-mono text-[11px] tracking-[0.22em] uppercase text-amber-600 dark:text-amber-500 mb-4">
                        <span class="text-stone-400 dark:text-stone-600">— 02</span> Audiencias
                    </div>
                    <h2 class="font-display text-4xl lg:text-5xl font-light leading-tight tracking-tight text-stone-900 dark:text-stone-50">
                        Tres caminos.
                        <span class="italic text-stone-500 dark:text-stone-400">Un sistema.</span>
                    </h2>
                </div>
                <div class="lg:col-span-7 lg:col-start-6">
                    <p class="font-display text-xl font-light leading-relaxed text-stone-600 dark:text-stone-300">
                        El mismo backend, tres interfaces especializadas. Cada una construida
                        para que su audiencia sea productiva sin distracciones.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-px bg-stone-200 dark:bg-stone-800 border border-stone-200 dark:border-stone-800">

                {{-- Usuario --}}
                <div class="track-card bg-white dark:bg-stone-900 p-8 lg:p-10" style="--track-accent: var(--confi-emerald);">
                    <div class="font-mono text-[11px] tracking-[0.22em] uppercase text-emerald-600 dark:text-emerald-500 mb-3">
                        Usuario final
                    </div>
                    <h3 class="font-display text-3xl font-medium text-stone-900 dark:text-stone-50 mb-4 leading-tight">
                        Portal del solicitante
                    </h3>
                    <p class="text-stone-600 dark:text-stone-400 leading-relaxed mb-6">
                        Crea solicitudes en segundos, sigue el progreso en tiempo real
                        y consulta el centro de ayuda sin fricción.
                    </p>
                    <ul class="space-y-2.5 text-sm text-stone-700 dark:text-stone-300">
                        <li class="flex gap-3"><span class="font-mono text-emerald-600 dark:text-emerald-500">→</span>Dashboard con stats personales</li>
                        <li class="flex gap-3"><span class="font-mono text-emerald-600 dark:text-emerald-500">→</span>Conversación tipo email con tu agente</li>
                        <li class="flex gap-3"><span class="font-mono text-emerald-600 dark:text-emerald-500">→</span>Centro de ayuda + asistente IA</li>
                        <li class="flex gap-3"><span class="font-mono text-emerald-600 dark:text-emerald-500">→</span>Encuesta de satisfacción al cerrar</li>
                    </ul>
                    <div class="mt-8 pt-6 border-t border-stone-100 dark:border-stone-800 font-mono text-[11px] tracking-widest text-stone-400">
                        / portal
                    </div>
                </div>

                {{-- Soporte --}}
                <div class="track-card bg-white dark:bg-stone-900 p-8 lg:p-10" style="--track-accent: var(--confi-sky);">
                    <div class="font-mono text-[11px] tracking-[0.22em] uppercase text-sky-600 dark:text-sky-500 mb-3">
                        Soporte
                    </div>
                    <h3 class="font-display text-3xl font-medium text-stone-900 dark:text-stone-50 mb-4 leading-tight">
                        Panel de agentes
                    </h3>
                    <p class="text-stone-600 dark:text-stone-400 leading-relaxed mb-6">
                        Atiende tickets con todo el contexto: SLA, historia,
                        plantillas y respuestas predefinidas a un click.
                    </p>
                    <ul class="space-y-2.5 text-sm text-stone-700 dark:text-stone-300">
                        <li class="flex gap-3"><span class="font-mono text-sky-600 dark:text-sky-500">→</span>Stats scoped por tu departamento</li>
                        <li class="flex gap-3"><span class="font-mono text-sky-600 dark:text-sky-500">→</span>Tomar ticket + plantilla en un paso</li>
                        <li class="flex gap-3"><span class="font-mono text-sky-600 dark:text-sky-500">→</span>Traslado entre departamentos</li>
                        <li class="flex gap-3"><span class="font-mono text-sky-600 dark:text-sky-500">→</span>Recalibrar prioridad con audit log</li>
                    </ul>
                    <div class="mt-8 pt-6 border-t border-stone-100 dark:border-stone-800 font-mono text-[11px] tracking-widest text-stone-400">
                        / soporte
                    </div>
                </div>

                {{-- Admin --}}
                <div class="track-card bg-white dark:bg-stone-900 p-8 lg:p-10" style="--track-accent: var(--confi-amber-bright);">
                    <div class="font-mono text-[11px] tracking-[0.22em] uppercase text-amber-600 dark:text-amber-500 mb-3">
                        Administración
                    </div>
                    <h3 class="font-display text-3xl font-medium text-stone-900 dark:text-stone-50 mb-4 leading-tight">
                        Panel global
                    </h3>
                    <p class="text-stone-600 dark:text-stone-400 leading-relaxed mb-6">
                        Configuración total del sistema, usuarios, SLA, inventario
                        y reportes ejecutivos para gerencia.
                    </p>
                    <ul class="space-y-2.5 text-sm text-stone-700 dark:text-stone-300">
                        <li class="flex gap-3"><span class="font-mono text-amber-600 dark:text-amber-500">→</span>Reporte SLA cross-departamental</li>
                        <li class="flex gap-3"><span class="font-mono text-amber-600 dark:text-amber-500">→</span>Configuración de departamentos</li>
                        <li class="flex gap-3"><span class="font-mono text-amber-600 dark:text-amber-500">→</span>Inventario completo + tokens API</li>
                        <li class="flex gap-3"><span class="font-mono text-amber-600 dark:text-amber-500">→</span>Respaldos automáticos programados</li>
                    </ul>
                    <div class="mt-8 pt-6 border-t border-stone-100 dark:border-stone-800 font-mono text-[11px] tracking-widest text-stone-400">
                        / admin
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ╔════════════════════════════════════════════════════════════════════╗
         │  CTA FINAL — gradiente industrial                                  │
         ╚════════════════════════════════════════════════════════════════════╝ --}}
    <section class="relative overflow-hidden bg-stone-950 text-stone-50">
        {{-- Halos --}}
        <div class="absolute -top-32 left-1/4 w-[420px] h-[420px] rounded-full bg-amber-500/30 blur-[120px] pointer-events-none"></div>
        <div class="absolute -bottom-32 right-1/4 w-[420px] h-[420px] rounded-full bg-orange-600/20 blur-[120px] pointer-events-none"></div>
        <div class="absolute inset-0 blueprint-grid opacity-50"></div>

        <div class="relative mx-auto max-w-5xl px-6 lg:px-10 py-24 lg:py-32 text-center">
            <div class="font-mono text-[11px] tracking-[0.22em] uppercase text-amber-500 mb-6">
                <span class="text-stone-600">— 03</span> Acceso
            </div>

            <h2 class="font-display text-4xl sm:text-5xl lg:text-7xl font-light leading-[1.05] tracking-tight">
                Inicia sesión con tu
                <span class="italic font-medium text-amber-500">cuenta corporativa</span>
            </h2>

            <p class="mt-8 max-w-xl mx-auto font-display text-xl font-light text-stone-300 leading-relaxed">
                Si ya tienes acceso a Confipetrol, no necesitas crear nada nuevo.
                Una sola entrada, todos los permisos por rol.
            </p>

            <div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-md bg-amber-500 hover:bg-amber-400 text-stone-950 px-8 py-4 text-base font-semibold transition shadow-2xl shadow-amber-500/30">
                        Ir al panel
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-md bg-amber-500 hover:bg-amber-400 text-stone-950 px-8 py-4 text-base font-semibold transition shadow-2xl shadow-amber-500/30">
                        Iniciar sesión
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>

                    @if(config('services.azure.client_id'))
                        <a href="{{ route('auth.azure') }}"
                           class="inline-flex items-center justify-center gap-2 rounded-md border border-stone-700 bg-stone-900/50 backdrop-blur px-8 py-4 text-base font-medium text-stone-200 hover:bg-stone-900 hover:border-stone-600 transition">
                            <svg class="w-4 h-4" viewBox="0 0 23 23" fill="currentColor"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                            Azure AD · SSO
                        </a>
                    @endif
                @endauth
            </div>

            <div class="mt-16 flex justify-center gap-6 font-mono text-[10px] tracking-[0.22em] uppercase text-stone-500">
                <span class="flex items-center gap-2">
                    <span class="status-dot w-1.5 h-1.5 rounded-full bg-emerald-500 text-emerald-500"></span>
                    Sistema operativo
                </span>
                <span class="text-stone-700">·</span>
                <span>Laravel {{ app()->version() }}</span>
            </div>
        </div>
    </section>

    {{-- ╔════════════════════════════════════════════════════════════════════╗
         │  FOOTER                                                            │
         ╚════════════════════════════════════════════════════════════════════╝ --}}
    <footer class="bg-stone-50 dark:bg-stone-950 border-t border-stone-200 dark:border-stone-800">
        <div class="mx-auto max-w-7xl px-6 lg:px-10 py-10">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol"
                         class="h-7 w-auto dark:hidden opacity-80">
                    <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol"
                         class="h-7 w-auto hidden dark:block opacity-80">
                    <div class="border-l border-stone-300 dark:border-stone-700 pl-3 ml-1">
                        <div class="font-mono text-[10px] tracking-[0.18em] uppercase text-stone-500">
                            Helpdesk Confipetrol
                        </div>
                        <div class="font-mono text-[10px] text-stone-400">
                            v1.9 · Plataforma interna · &copy; {{ date('Y') }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-6 font-mono text-[10px] tracking-[0.18em] uppercase text-stone-500">
                    <a href="#sistema" class="hover:text-amber-600 transition">Sistema</a>
                    <a href="#tracks" class="hover:text-amber-600 transition">Audiencias</a>
                    <a href="{{ route('login') }}" class="hover:text-amber-600 transition">Acceder</a>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-stone-200 dark:border-stone-800 flex flex-col sm:flex-row items-center justify-between gap-4 font-mono text-[10px] text-stone-400">
                <div>
                    Construido con Laravel · Filament · Livewire · Tailwind v4
                </div>
                <div class="flex items-center gap-2">
                    <span class="status-dot inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 text-emerald-500"></span>
                    <span class="tracking-[0.18em] uppercase">Sistema operativo · {{ now()->translatedFormat('d M H:i') }}</span>
                </div>
            </div>
        </div>
    </footer>

</main>

</body>
</html>
