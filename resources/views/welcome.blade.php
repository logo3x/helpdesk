<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Helpdesk Confipetrol — plataforma interna de soporte técnico. Crea tickets, consulta la base de conocimiento y habla con un asistente virtual.">
    <meta name="theme-color" content="#f59e0b">

    <title>Helpdesk Confipetrol — Soporte interno</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Scroll snap por secciones en desktop/tablet, libre en móvil para evitar saltos bruscos */
        @media (min-width: 768px) and (min-height: 600px) {
            .snap-container {
                scroll-snap-type: y mandatory;
                scroll-behavior: smooth;
                height: 100vh;
                overflow-y: auto;
            }
            .snap-section {
                scroll-snap-align: start;
                scroll-snap-stop: always;
                min-height: 100vh;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .snap-container { scroll-snap-type: none; }
            html { scroll-behavior: auto; }
        }

        /* Scrollbar más sutil en el contenedor principal */
        .snap-container::-webkit-scrollbar { width: 8px; }
        .snap-container::-webkit-scrollbar-track { background: transparent; }
        .snap-container::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 4px; }
        .dark .snap-container::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); }

        /* Animación sutil de entrada */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeInUp 0.6s ease-out both; }
    </style>
</head>
<body class="antialiased bg-white text-zinc-800 dark:bg-zinc-950 dark:text-zinc-100 font-sans">

<div class="snap-container">

    {{-- ════════════════════════════════════════════════════════════════════
         Hero
    ════════════════════════════════════════════════════════════════════ --}}
    <section class="snap-section relative flex items-center justify-center overflow-hidden
                    bg-gradient-to-br from-amber-50 via-white to-sky-50
                    dark:from-zinc-900 dark:via-zinc-950 dark:to-sky-950">

        {{-- Top bar --}}
        <header class="absolute top-0 inset-x-0 z-10">
            <nav class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                <a href="/" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-confipetrol-dark.png') }}"
                         alt="Confipetrol"
                         class="h-8 w-auto dark:hidden">
                    <img src="{{ asset('images/logo-confipetrol.png') }}"
                         alt="Confipetrol"
                         class="h-8 w-auto hidden dark:block">
                    <span class="hidden sm:inline text-sm font-medium text-zinc-600 dark:text-zinc-400">Helpdesk</span>
                </a>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-600 transition">
                            Ir al panel
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center rounded-lg border border-zinc-300 dark:border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                            Iniciar sesión
                        </a>
                    @endauth
                </div>
            </nav>
        </header>

        {{-- Hero content --}}
        <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 pt-24 sm:pt-20 pb-20 text-center fade-in-up">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 dark:bg-amber-950/50 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-900">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                Plataforma interna · Confipetrol
            </span>

            <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-zinc-900 dark:text-white leading-[1.1]">
                Soporte interno
                <span class="block bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-transparent">
                    más rápido y organizado
                </span>
            </h1>

            <p class="mt-6 text-lg sm:text-xl text-zinc-600 dark:text-zinc-300 max-w-2xl mx-auto leading-relaxed">
                Crea tickets, consulta la base de conocimiento o conversa con el asistente virtual.
                Todo desde un mismo lugar, para TI, RRHH, Compras, Mantenimiento y Operaciones.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                @auth
                    <a href="{{ route('portal.tickets.create') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 hover:bg-amber-600 transition">
                        Crear un ticket
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </a>
                    <a href="{{ route('portal.chatbot') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 dark:border-zinc-700 px-6 py-3 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-white dark:hover:bg-zinc-800 transition">
                        Abrir asistente virtual
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 hover:bg-amber-600 transition">
                        Iniciar sesión
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    <a href="#features"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg border border-zinc-300 dark:border-zinc-700 px-6 py-3 text-sm font-semibold text-zinc-700 dark:text-zinc-200 hover:bg-white dark:hover:bg-zinc-800 transition">
                        Conocer más
                    </a>
                @endauth
            </div>
        </div>

        {{-- Indicador de scroll (solo desktop) --}}
        <button type="button"
                onclick="document.getElementById('features').scrollIntoView({behavior:'smooth'})"
                class="absolute bottom-6 left-1/2 -translate-x-1/2 hidden md:flex flex-col items-center text-zinc-400 dark:text-zinc-500 hover:text-amber-500 transition"
                aria-label="Siguiente sección">
            <span class="text-xs font-medium uppercase tracking-widest">Descubre más</span>
            <svg class="w-5 h-5 mt-2 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            </svg>
        </button>
    </section>

    {{-- ════════════════════════════════════════════════════════════════════
         Features
    ════════════════════════════════════════════════════════════════════ --}}
    <section id="features"
             class="snap-section relative flex items-center py-16 sm:py-20
                    bg-white dark:bg-zinc-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
            <div class="text-center mb-12 lg:mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    Todo lo que necesitas, en un solo lugar
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                    Tres herramientas integradas que se adaptan a cómo trabaja tu equipo.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                {{-- Feature 1: Tickets --}}
                <div class="group rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 lg:p-8 transition hover:shadow-lg hover:border-amber-300 dark:hover:border-amber-700">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-2">Sistema de Tickets</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Crea, asigna y resuelve solicitudes con prioridad automática (ITIL),
                        notificaciones por email y SLA monitoreado 24/7.
                    </p>
                </div>

                {{-- Feature 2: Chatbot --}}
                <div class="group rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 lg:p-8 transition hover:shadow-lg hover:border-sky-300 dark:hover:border-sky-700">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-2">Asistente virtual</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Chatbot híbrido con flujos guiados + búsqueda en la base de conocimiento.
                        Resuelve dudas comunes sin esperar a un agente.
                    </p>
                </div>

                {{-- Feature 3: KB --}}
                <div class="group rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-6 lg:p-8 transition hover:shadow-lg hover:border-emerald-300 dark:hover:border-emerald-700">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-2">Base de Conocimiento</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Artículos por departamento, flujo de aprobación por supervisor
                        y contenido integrado al asistente virtual.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════════════════════
         Para quién (3 paneles)
    ════════════════════════════════════════════════════════════════════ --}}
    <section class="snap-section relative flex items-center py-16 sm:py-20
                    bg-gradient-to-br from-zinc-50 to-amber-50 dark:from-zinc-900 dark:to-zinc-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 w-full">
            <div class="text-center mb-12 lg:mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">
                    Diseñado por rol
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                    Tres interfaces especializadas, cada una con lo que realmente necesitas ver.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                {{-- Admin --}}
                <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 p-[1px] shadow-xl">
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 p-6 lg:p-8 h-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                            <span class="text-xs font-semibold tracking-widest text-amber-600 dark:text-amber-400 uppercase">Administración</span>
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Panel Admin</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 mb-4">Configuración global, usuarios, SLA y reportes ejecutivos.</p>
                        <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <li class="flex gap-2"><span class="text-amber-500">✓</span>Gestión de usuarios y roles</li>
                            <li class="flex gap-2"><span class="text-amber-500">✓</span>Departamentos y categorías</li>
                            <li class="flex gap-2"><span class="text-amber-500">✓</span>Reporte SLA y auditoría</li>
                            <li class="flex gap-2"><span class="text-amber-500">✓</span>Respaldos automáticos</li>
                        </ul>
                    </div>
                </div>

                {{-- Soporte --}}
                <div class="rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 p-[1px] shadow-xl">
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 p-6 lg:p-8 h-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span>
                            <span class="text-xs font-semibold tracking-widest text-sky-600 dark:text-sky-400 uppercase">Soporte</span>
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Panel Soporte</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 mb-4">Atiende tickets con el contexto que necesitas y herramientas rápidas.</p>
                        <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <li class="flex gap-2"><span class="text-sky-500">✓</span>Vista scoped por depto</li>
                            <li class="flex gap-2"><span class="text-sky-500">✓</span>Plantillas y respuestas rápidas</li>
                            <li class="flex gap-2"><span class="text-sky-500">✓</span>Traslado entre departamentos</li>
                            <li class="flex gap-2"><span class="text-sky-500">✓</span>Base de conocimiento editable</li>
                        </ul>
                    </div>
                </div>

                {{-- Portal --}}
                <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-[1px] shadow-xl">
                    <div class="rounded-2xl bg-white dark:bg-zinc-900 p-6 lg:p-8 h-full">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-semibold tracking-widest text-emerald-600 dark:text-emerald-400 uppercase">Usuario</span>
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Portal del usuario</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 mb-4">Experiencia simple para crear y dar seguimiento a tus solicitudes.</p>
                        <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <li class="flex gap-2"><span class="text-emerald-500">✓</span>Crear ticket en segundos</li>
                            <li class="flex gap-2"><span class="text-emerald-500">✓</span>Seguimiento en tiempo real</li>
                            <li class="flex gap-2"><span class="text-emerald-500">✓</span>Asistente virtual 24/7</li>
                            <li class="flex gap-2"><span class="text-emerald-500">✓</span>Historial completo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════════════════════
         CTA final
    ════════════════════════════════════════════════════════════════════ --}}
    <section class="snap-section relative flex items-center py-16 sm:py-20
                    bg-gradient-to-br from-amber-500 via-orange-600 to-red-600">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-white">
                Listo para empezar
            </h2>
            <p class="mt-6 text-lg sm:text-xl text-amber-50 max-w-2xl mx-auto leading-relaxed">
                Inicia sesión con tu cuenta corporativa y comienza a gestionar tus solicitudes
                de forma más eficiente.
            </p>

            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-white px-8 py-3.5 text-base font-semibold text-amber-600 shadow-xl hover:bg-amber-50 transition">
                        Ir al dashboard
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-white px-8 py-3.5 text-base font-semibold text-amber-600 shadow-xl hover:bg-amber-50 transition">
                        Iniciar sesión
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                    @if(config('services.azure.client_id'))
                        <a href="{{ route('auth.azure') }}"
                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg border-2 border-white/40 bg-white/10 px-8 py-3.5 text-base font-semibold text-white backdrop-blur-sm hover:bg-white/20 transition">
                            <svg class="w-5 h-5" viewBox="0 0 23 23" fill="currentColor"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                            Continuar con Microsoft
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════════════════════════════
         Footer (compacto, sin snap)
    ════════════════════════════════════════════════════════════════════ --}}
    <footer class="border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-zinc-500 dark:text-zinc-400">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/logo-confipetrol-dark.png') }}"
                     alt="Confipetrol"
                     class="h-6 w-auto dark:hidden opacity-75">
                <img src="{{ asset('images/logo-confipetrol.png') }}"
                     alt="Confipetrol"
                     class="h-6 w-auto hidden dark:block opacity-75">
                <span>&copy; {{ date('Y') }} Confipetrol. Plataforma interna.</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="hidden md:inline">Laravel {{ app()->version() }}</span>
                <span class="text-emerald-600 dark:text-emerald-400">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-1"></span>
                    Operativo
                </span>
            </div>
        </div>
    </footer>

</div>

</body>
</html>
