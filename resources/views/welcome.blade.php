<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Helpdesk Confipetrol — pide ayuda técnica, sigue tu caso en tiempo real y consulta el centro de ayuda. Rápido y sin correos.">
    <meta name="theme-color" content="#fafaf9">

    <title>Helpdesk · Confipetrol</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:300,400,500,600,700|ibm-plex-sans:400,500,600|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /*
         * Hallmark · macrostructure: Narrative Workflow · tone: empático-claro · anchor hue: amber
         * pre-emit critique: P5 H5 E5 S5 R5 V5
         * genre: editorial · nav: N5 floating pill · footer: Ft1 minimal
         * designed-as-app: helpdesk-confipetrol · audience: usuario-final · v2-beneficios
         */

        :root {
            --color-accent:          oklch(62% 0.15 60);
            --color-accent-deep:     oklch(50% 0.14 55);
            --color-accent-glow:     oklch(62% 0.15 60 / 0.18);
            --color-accent-ink:      oklch(15% 0.03 50);
            --color-accent-surface:  oklch(98.5% 0.014 62);
            --color-accent-border:   oklch(87% 0.05 62);
            --color-emerald:         oklch(55% 0.14 158);
            --color-emerald-surface: oklch(97% 0.018 155);
            --color-sky:             oklch(52% 0.13 232);
            --color-sky-surface:     oklch(96.5% 0.018 230);
            --color-rule:            oklch(90% 0.004 60);
            --color-rule-dark:       oklch(20% 0.006 60);
            --color-paper:           oklch(99% 0.002 60);
            --color-paper-warm:      oklch(97% 0.008 60);
            --color-paper-dark:      oklch(9% 0.007 60);
            --color-ink:             oklch(15% 0.01 60);
            --color-ink-2:           oklch(38% 0.008 60);
            --color-ink-3:           oklch(56% 0.006 60);

            --font-display: 'Fraunces', Georgia, serif;
            --font-body:    'IBM Plex Sans', system-ui, sans-serif;
            --font-mono:    'IBM Plex Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;

            --ease-out:  cubic-bezier(0.16, 1, 0.3, 1);
            --dur-base:  240ms;
            --dur-fast:  150ms;

            --space-2xs: 0.375rem;
            --space-xs:  0.75rem;
            --space-sm:  1rem;
            --space-md:  1.5rem;
            --space-lg:  2.5rem;
            --space-xl:  4rem;
            --space-2xl: 6.5rem;

            --radius-sm:  4px;
            --radius-md:  8px;
            --radius-lg:  14px;
            --radius-xl:  20px;
        }

        html, body { overflow-x: clip; }

        body {
            font-family: var(--font-body);
            font-feature-settings: 'ss01', 'cv11';
            background: var(--color-paper);
            color: var(--color-ink);
        }

        .dark body {
            background: var(--color-paper-dark);
            color: oklch(91% 0.004 60);
        }

        .font-display {
            font-family: var(--font-display);
            font-optical-sizing: auto;
            letter-spacing: -0.03em;
        }
        .font-mono { font-family: var(--font-mono); }

        /* ── Botón primario ──────────────────────────────────────── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--color-accent);
            color: var(--color-accent-ink);
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 1px 4px var(--color-accent-glow);
            transition: background var(--dur-fast) var(--ease-out),
                        box-shadow  var(--dur-fast) var(--ease-out),
                        transform   var(--dur-fast) var(--ease-out);
        }
        .btn-primary:hover  { background: var(--color-accent-deep); box-shadow: 0 4px 14px var(--color-accent-glow); }
        .btn-primary:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 3px; }
        .btn-primary:active { transform: translateY(1px); }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1.5px solid var(--color-rule);
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 500;
            white-space: nowrap;
            background: transparent;
            transition: border-color var(--dur-fast) var(--ease-out), background var(--dur-fast) var(--ease-out);
        }
        .btn-ghost:hover { border-color: var(--color-ink-3); background: oklch(96% 0.004 60 / 0.6); }
        .btn-ghost:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 3px; }
        .dark .btn-ghost { border-color: var(--color-rule-dark); color: oklch(84% 0.005 60); }
        .dark .btn-ghost:hover { border-color: oklch(42% 0.006 60); background: oklch(16% 0.006 60); }

        /* ── Dot pulsante ────────────────────────────────────────── */
        @keyframes dot-pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }
        .dot-live {
            display: inline-block;
            width: 7px; height: 7px;
            border-radius: 9999px;
            background: var(--color-emerald);
            flex-shrink: 0;
            animation: dot-pulse 2s ease-in-out infinite;
        }

        /* ── Tarjeta de beneficio ────────────────────────────────── */
        .benefit-card {
            padding: var(--space-lg) var(--space-lg) var(--space-xl);
            border-radius: var(--radius-xl);
            position: relative;
            overflow: hidden;
        }

        /* ── Etapa de workflow ───────────────────────────────────── */
        .stage {
            display: grid;
            grid-template-columns: 96px 1fr;
            gap: 0 var(--space-xl);
            align-items: start;
            padding: var(--space-xl) 0;
            border-top: 1px solid var(--color-rule);
        }
        .dark .stage { border-color: var(--color-rule-dark); }

        @media (max-width: 640px) {
            .stage { grid-template-columns: 1fr; gap: var(--space-sm); }
        }

        .stage-number {
            font-family: var(--font-display);
            font-size: clamp(3.5rem, 7vw, 5rem);
            font-weight: 300;
            color: var(--color-accent);
            line-height: 1;
            letter-spacing: -0.04em;
            opacity: 0.7;
            padding-top: 0.15em;
        }

        /* ── Servicios ───────────────────────────────────────────── */
        .service-item {
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
            padding: var(--space-md);
            border: 1px solid var(--color-rule);
            border-radius: var(--radius-lg);
            background: white;
            transition: border-color var(--dur-base) var(--ease-out),
                        box-shadow  var(--dur-base) var(--ease-out);
        }
        .service-item:hover {
            border-color: oklch(78% 0.008 60);
            box-shadow: 0 3px 12px oklch(30% 0.005 60 / 0.07);
        }
        .dark .service-item { background: oklch(13.5% 0.007 60); border-color: var(--color-rule-dark); }
        .dark .service-item:hover { border-color: oklch(33% 0.007 60); }

        /* ── Demo card (hero) ────────────────────────────────────── */
        .demo-card {
            border: 1px solid var(--color-rule);
            border-radius: var(--radius-xl);
            background: white;
            overflow: hidden;
            box-shadow: 0 12px 40px oklch(20% 0.005 60 / 0.1), 0 2px 8px oklch(20% 0.005 60 / 0.05);
        }
        .dark .demo-card {
            background: oklch(13% 0.007 60);
            border-color: var(--color-rule-dark);
            box-shadow: 0 12px 40px oklch(0% 0 0 / 0.45);
        }

        /* ── Animaciones ─────────────────────────────────────────── */
        @keyframes enter-up {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .enter   { animation: enter-up 0.55s var(--ease-out) both; }
        .enter-1 { animation-delay: 0.05s; }
        .enter-2 { animation-delay: 0.14s; }
        .enter-3 { animation-delay: 0.26s; }
        .enter-4 { animation-delay: 0.40s; }
        .enter-5 { animation-delay: 0.56s; }

        @media (prefers-reduced-motion: reduce) {
            .enter, .dot-live { animation: none !important; opacity: 1; }
        }
    </style>
</head>
<body class="antialiased selection:bg-amber-200 selection:text-stone-900 dark:selection:bg-amber-900/60 dark:selection:text-amber-100">

{{-- ═══════════════════════════════════════════════
     NAV — N5 floating pill
     ═══════════════════════════════════════════════ --}}
<header class="fixed top-0 inset-x-0 z-50 flex justify-center pt-3 px-4">
    <nav aria-label="Navegación principal"
         class="w-full max-w-5xl flex items-center justify-between px-4 py-2.5 rounded-2xl bg-white/90 dark:bg-stone-900/90 backdrop-blur-md border border-stone-200/80 dark:border-stone-800/70 shadow-sm shadow-stone-900/5">

        <a href="/" aria-label="Helpdesk Confipetrol — Inicio" class="flex items-center gap-3">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="" aria-hidden="true"
                 class="h-6 w-auto dark:hidden">
            <img src="{{ asset('images/logo-confipetrol.png') }}" alt="" aria-hidden="true"
                 class="h-6 w-auto hidden dark:block">
            <span class="hidden sm:block font-mono text-[10px] tracking-[0.2em] uppercase text-stone-500 dark:text-stone-400 pl-3 ml-0.5 border-l border-stone-200 dark:border-stone-800">
                Helpdesk
            </span>
        </a>

        <div class="hidden md:flex items-center gap-6 font-mono text-[10px] tracking-[0.18em] uppercase text-stone-400 dark:text-stone-500">
            <a href="#beneficios"    class="hover:text-stone-700 dark:hover:text-stone-300 transition-colors">Beneficios</a>
            <a href="#como-funciona" class="hover:text-stone-700 dark:hover:text-stone-300 transition-colors">Cómo funciona</a>
            <a href="#servicios"     class="hover:text-stone-700 dark:hover:text-stone-300 transition-colors">Servicios</a>
        </div>

        <div class="flex items-center gap-3">
            <span class="hidden sm:flex items-center gap-2 font-mono text-[10px] tracking-widest text-emerald-600 dark:text-emerald-500">
                <span class="dot-live"></span>
                Disponible
            </span>
            <span class="hidden sm:block w-px h-3.5 bg-stone-200 dark:bg-stone-800"></span>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary !py-2 !px-4 !text-sm">
                    Ir a mis tickets
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-primary !py-2 !px-4 !text-sm">
                    Ingresar
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            @endauth
        </div>
    </nav>
</header>

<main class="pt-20 sm:pt-24">

    {{-- ═══════════════════════════════════════════════
         HERO
         ═══════════════════════════════════════════════ --}}
    <section class="relative bg-white dark:bg-stone-950">
        <div class="absolute inset-0 bg-gradient-to-b from-amber-50/60 to-transparent dark:from-amber-950/15 dark:to-transparent pointer-events-none" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-5xl px-6 lg:px-8 pt-16 pb-24 md:pt-20 md:pb-32">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-14 lg:gap-10 items-center">

                {{-- Texto --}}
                <div>
                    <div class="enter enter-1 inline-flex items-center gap-2.5 mb-7 px-3 py-1.5 rounded-full border border-amber-200 dark:border-amber-800/60 bg-amber-50 dark:bg-amber-950/40">
                        <span class="dot-live" style="background: var(--color-accent);"></span>
                        <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-amber-700 dark:text-amber-500">
                            Soporte técnico interno · Confipetrol
                        </span>
                    </div>

                    <h1 class="enter enter-2 font-display font-light text-stone-900 dark:text-stone-50 leading-[1.05]"
                        style="font-size: clamp(2.4rem, 5.5vw, 4rem); overflow-wrap: anywhere; min-width: 0;">
                        ¿Tienes un<br>problema técnico?<br>
                        <span class="font-semibold" style="color: var(--color-accent);">Lo resolvemos.</span>
                    </h1>

                    <p class="enter enter-3 mt-6 text-base sm:text-lg text-stone-600 dark:text-stone-300 leading-relaxed" style="max-width: 38ch;">
                        Una sola plataforma para reportar fallas, seguir tu caso
                        y consultar la base de conocimiento de TI.
                        Sin correos, sin llamadas, sin espera.
                    </p>

                    <div class="enter enter-4 mt-8 flex flex-wrap items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary">
                                Ver mis solicitudes
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary">
                                Pedir ayuda ahora
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                            @if(config('services.azure.client_id'))
                                <a href="{{ route('auth.azure') }}" class="btn-ghost text-sm">
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 23 23" fill="currentColor" aria-hidden="true"><path d="M1 1h10v10H1zM12 1h10v10H12zM1 12h10v10H1zM12 12h10v10H12z"/></svg>
                                    Entrar con Azure
                                </a>
                            @endif
                        @endauth
                    </div>

                    <p class="enter enter-5 mt-5 text-xs text-stone-400 dark:text-stone-500">
                        Accede con tu correo corporativo · Tu cuenta ya existe
                    </p>
                </div>

                {{-- Demo: formulario de solicitud --}}
                <div class="enter enter-3 lg:pl-4">
                    <div class="demo-card">
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-stone-100 dark:border-stone-800"
                             style="background: var(--color-paper-warm);">
                            <div class="flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: var(--color-accent);"></div>
                                <span class="font-mono text-[10px] tracking-[0.2em] uppercase text-stone-500 dark:text-stone-400">Nueva solicitud</span>
                            </div>
                            <span class="font-mono text-[10px] text-stone-400 dark:text-stone-500">Helpdesk</span>
                        </div>

                        <div class="p-5 space-y-4">
                            <div>
                                <label class="block font-mono text-[10px] tracking-widest uppercase text-stone-400 dark:text-stone-500 mb-1.5">¿Qué tipo de problema tienes?</label>
                                <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-800 text-sm text-stone-700 dark:text-stone-300">
                                    <svg class="w-4 h-4 text-stone-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    Computador / hardware
                                    <svg class="w-3.5 h-3.5 ml-auto text-stone-300 dark:text-stone-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>

                            <div>
                                <label class="block font-mono text-[10px] tracking-widest uppercase text-stone-400 dark:text-stone-500 mb-1.5">Cuéntanos qué pasó</label>
                                <div class="px-3 py-2.5 rounded-lg border-2 text-sm text-stone-700 dark:text-stone-300 leading-relaxed" style="border-color: var(--color-accent); background: var(--color-accent-surface);">
                                    Mi computador no se conecta al wifi desde esta mañana. Probé reiniciarlo y sigue igual.
                                    <span class="inline-block w-0.5 h-4 ml-0.5 align-text-bottom rounded-full animate-pulse" style="background: var(--color-accent);"></span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <span class="text-xs text-stone-400 dark:text-stone-500">Tu agente responde pronto</span>
                                <span class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background: var(--color-accent);">
                                    Enviar
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 text-center font-mono text-[10px] tracking-[0.18em] text-stone-400 dark:text-stone-500">
                        Así se ve cuando abres una solicitud
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         BENEFICIOS — fondo oscuro, contraste visual
         Por qué usar el Helpdesk en lugar de llamar/correo
         ═══════════════════════════════════════════════ --}}
    <section id="beneficios" class="bg-stone-950 py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6 lg:px-8">

            <div class="mb-14">
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-stone-500 block mb-4">Por qué usarlo</span>
                <h2 class="font-display text-3xl sm:text-4xl font-light text-stone-50 leading-tight" style="overflow-wrap: anywhere; min-width: 0;">
                    Más rápido que un correo.<br>
                    <span class="font-medium" style="color: var(--color-accent);">Más claro que una llamada.</span>
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- Beneficio 1: Seguimiento en tiempo real --}}
                <div class="benefit-card" style="background: oklch(13% 0.008 60); border: 1px solid oklch(20% 0.006 60);">
                    <div class="mb-5 w-11 h-11 rounded-xl flex items-center justify-center" style="background: oklch(18% 0.012 60);">
                        <svg class="w-5 h-5" style="color: var(--color-accent);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-50 mb-3 leading-snug">
                        Siempre sabes dónde está tu caso
                    </h3>
                    <p class="text-sm text-stone-400 leading-relaxed">
                        Cada solicitud tiene un estado visible en tiempo real: si está asignada, en progreso o resuelta.
                        No tienes que preguntar "¿alguien vio mi correo?".
                    </p>
                </div>

                {{-- Beneficio 2: Historial permanente --}}
                <div class="benefit-card" style="background: oklch(13% 0.008 60); border: 1px solid oklch(20% 0.006 60);">
                    <div class="mb-5 w-11 h-11 rounded-xl flex items-center justify-center" style="background: oklch(18% 0.012 60);">
                        <svg class="w-5 h-5" style="color: var(--color-emerald);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-50 mb-3 leading-snug">
                        Tu historial nunca desaparece
                    </h3>
                    <p class="text-sm text-stone-400 leading-relaxed">
                        Todos tus casos quedan guardados con la conversación completa y lo que se hizo para resolverlos.
                        Si el problema regresa, el agente ya sabe el contexto.
                    </p>
                </div>

                {{-- Beneficio 3: Respuesta garantizada --}}
                <div class="benefit-card" style="background: oklch(13% 0.008 60); border: 1px solid oklch(20% 0.006 60);">
                    <div class="mb-5 w-11 h-11 rounded-xl flex items-center justify-center" style="background: oklch(18% 0.012 60);">
                        <svg class="w-5 h-5" style="color: var(--color-sky);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-medium text-stone-50 mb-3 leading-snug">
                        Tu solicitud no se puede perder
                    </h3>
                    <p class="text-sm text-stone-400 leading-relaxed">
                        El sistema asigna automáticamente tu caso al equipo correcto y monitorea que tenga respuesta.
                        Si pasa demasiado tiempo sin atención, se escala solo.
                    </p>
                </div>
            </div>

            {{-- Segunda fila de beneficios --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

                {{-- Beneficio 4: Centro de ayuda --}}
                <div class="benefit-card flex gap-5" style="background: oklch(13% 0.008 60); border: 1px solid oklch(20% 0.006 60); padding: var(--space-md) var(--space-lg);">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center mt-1" style="background: oklch(18% 0.012 60);">
                        <svg class="w-5 h-5" style="color: oklch(68% 0.13 280);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-display text-lg font-medium text-stone-50 mb-2 leading-snug">
                            Centro de ayuda con asistente IA
                        </h3>
                        <p class="text-sm text-stone-400 leading-relaxed">
                            Antes de abrir un ticket, consulta el centro de ayuda. Hay artículos escritos por el equipo de TI
                            y un asistente que responde preguntas frecuentes al instante — sin esperar a que nadie conteste.
                        </p>
                    </div>
                </div>

                {{-- Beneficio 5: Mis activos --}}
                <div class="benefit-card flex gap-5" style="background: oklch(13% 0.008 60); border: 1px solid oklch(20% 0.006 60); padding: var(--space-md) var(--space-lg);">
                    <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center mt-1" style="background: oklch(18% 0.012 60);">
                        <svg class="w-5 h-5" style="color: oklch(65% 0.12 35);" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-display text-lg font-medium text-stone-50 mb-2 leading-snug">
                            Tus equipos asignados, siempre visibles
                        </h3>
                        <p class="text-sm text-stone-400 leading-relaxed">
                            En "Mis activos" puedes ver los equipos que TI tiene registrados a tu nombre,
                            aceptar formalmente la entrega y descargar el acta si la necesitas.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         NARRATIVE WORKFLOW — 3 pasos desde el empleado
         ═══════════════════════════════════════════════ --}}
    <section id="como-funciona" class="bg-stone-50 dark:bg-stone-950 border-t border-stone-100 dark:border-stone-900 py-24 lg:py-32">
        <div class="mx-auto max-w-5xl px-6 lg:px-8">

            <div class="mb-14">
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-stone-400 dark:text-stone-500 block mb-4">El proceso</span>
                <h2 class="font-display text-3xl sm:text-4xl font-light text-stone-900 dark:text-stone-50 leading-tight" style="overflow-wrap: anywhere; min-width: 0;">
                    Así funciona <span class="font-medium" style="color: var(--color-accent);">para ti.</span>
                </h2>
            </div>

            {{-- Etapa 01 --}}
            <div class="stage">
                <div class="stage-number">01</div>
                <div>
                    <h3 class="font-display text-2xl sm:text-3xl font-medium text-stone-900 dark:text-stone-50 mb-3 leading-snug">
                        Abres tu solicitud
                    </h3>
                    <p class="text-base text-stone-600 dark:text-stone-300 leading-relaxed mb-5" style="max-width: 50ch;">
                        Inicia sesión con tu correo de Confipetrol, describe el problema con tus propias palabras
                        y selecciona la categoría. El sistema lo enruta al equipo correcto de forma automática.
                        Todo el proceso toma menos de dos minutos.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium border border-stone-200 dark:border-stone-700 text-stone-600 dark:text-stone-400 bg-white dark:bg-stone-800">
                            <svg class="w-3 h-3 shrink-0" style="color: var(--color-accent);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Sin instalar nada
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium border border-stone-200 dark:border-stone-700 text-stone-600 dark:text-stone-400 bg-white dark:bg-stone-800">
                            <svg class="w-3 h-3 shrink-0" style="color: var(--color-accent);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Desde cualquier dispositivo
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium border border-stone-200 dark:border-stone-700 text-stone-600 dark:text-stone-400 bg-white dark:bg-stone-800">
                            <svg class="w-3 h-3 shrink-0" style="color: var(--color-accent);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Adjunta capturas o fotos
                        </span>
                    </div>
                </div>
            </div>

            {{-- Etapa 02 --}}
            <div class="stage">
                <div class="stage-number">02</div>
                <div>
                    <h3 class="font-display text-2xl sm:text-3xl font-medium text-stone-900 dark:text-stone-50 mb-3 leading-snug">
                        Tu agente lo toma y actúa
                    </h3>
                    <p class="text-base text-stone-600 dark:text-stone-300 leading-relaxed mb-5" style="max-width: 50ch;">
                        Un agente del área correspondiente recibe tu caso y te escribe para confirmarte que lo tomó.
                        Puedes responderle directamente desde la plataforma como si fuera un chat.
                        Ves en tiempo real si el caso está siendo trabajado.
                    </p>

                    <div class="rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 overflow-hidden" style="max-width: 420px;">
                        <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-800 flex items-center gap-2">
                            <span class="dot-live"></span>
                            <span class="font-mono text-[10px] tracking-widest text-stone-400 dark:text-stone-500 uppercase">Caso #TK-2481 · En progreso</span>
                        </div>
                        <div class="p-4 space-y-3">
                            <div class="flex gap-2.5">
                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-semibold text-white" style="background: var(--color-emerald);">A</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-stone-700 dark:text-stone-300 mb-0.5">Agente TI</p>
                                    <div class="bg-stone-50 dark:bg-stone-800 rounded-lg px-3 py-2 text-sm text-stone-700 dark:text-stone-300 leading-snug">
                                        Hola, tomé tu caso. ¿El wifi no aparece en la lista de redes o aparece pero no conecta?
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2.5 flex-row-reverse">
                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-semibold text-white" style="background: var(--color-accent);">M</div>
                                <div class="flex-1 min-w-0 text-right">
                                    <p class="text-xs font-medium text-stone-700 dark:text-stone-300 mb-0.5">Tú</p>
                                    <div class="inline-block text-left rounded-lg px-3 py-2 text-sm leading-snug text-white" style="background: var(--color-accent);">
                                        Aparece pero dice "sin internet"
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Etapa 03 --}}
            <div class="stage" style="border-bottom: 1px solid var(--color-rule);">
                <div class="stage-number">03</div>
                <div>
                    <h3 class="font-display text-2xl sm:text-3xl font-medium text-stone-900 dark:text-stone-50 mb-3 leading-snug">
                        Problema resuelto y registrado
                    </h3>
                    <p class="text-base text-stone-600 dark:text-stone-300 leading-relaxed mb-5" style="max-width: 50ch;">
                        Cuando tu caso se cierra, recibes una notificación con el resumen de lo que se hizo.
                        Puedes calificar la atención, consultar el historial de tus casos anteriores
                        o reabrir el caso si el problema regresa.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="inline-flex items-center gap-3 rounded-xl border px-4 py-3" style="border-color: var(--color-emerald); background: var(--color-emerald-surface);">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center" style="background: var(--color-emerald);">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium" style="color: oklch(38% 0.12 158);">Caso #TK-2481 · Resuelto</p>
                                <p class="text-xs text-stone-500">Se actualizó el perfil de red · Hoy 14:32</p>
                            </div>
                        </div>

                        <div class="inline-flex items-center gap-3 rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-800 px-4 py-3">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-stone-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-stone-700 dark:text-stone-300">Califica la atención</p>
                                <p class="text-xs text-stone-400">Tu opinión mejora el servicio</p>
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
    <section id="servicios" class="bg-white dark:bg-stone-950 py-24 lg:py-32 border-t border-stone-100 dark:border-stone-900">
        <div class="mx-auto max-w-5xl px-6 lg:px-8">

            <div class="mb-12">
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-stone-400 dark:text-stone-500 block mb-4">Categorías</span>
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <h2 class="font-display text-3xl sm:text-4xl font-light text-stone-900 dark:text-stone-50 leading-tight" style="overflow-wrap: anywhere; min-width: 0;">
                        ¿Qué puedes pedir?
                    </h2>
                    <p class="text-sm text-stone-400 dark:text-stone-500 sm:text-right sm:max-w-[26ch] leading-relaxed">
                        Si tu problema no encaja exactamente en ninguna categoría, descríbelo y lo asignamos nosotros.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

                <div class="service-item">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center" style="background: var(--color-accent-surface);">
                        <svg style="color: var(--color-accent); width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-stone-800 dark:text-stone-200 mb-1">Mi computador / equipo</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">Lento, no enciende, pantalla dañada, teclado o ratón con fallas</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center" style="background: oklch(96% 0.018 232);">
                        <svg style="color: var(--color-sky); width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-stone-800 dark:text-stone-200 mb-1">Internet y red</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">Sin conexión, wifi lento, VPN, problemas de acceso remoto</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center" style="background: var(--color-emerald-surface);">
                        <svg style="color: var(--color-emerald); width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-stone-800 dark:text-stone-200 mb-1">Accesos y contraseñas</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">Olvidé mi contraseña, no puedo entrar a un sistema, solicitar acceso nuevo</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center bg-stone-100 dark:bg-stone-800">
                        <svg style="color: oklch(45% 0.007 60); width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-stone-800 dark:text-stone-200 mb-1">Impresoras y periféricos</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">No imprime, scanner, cámara web, monitores o dispositivos externos</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center" style="background: oklch(97% 0.012 280);">
                        <svg style="color: oklch(52% 0.13 280); width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-stone-800 dark:text-stone-200 mb-1">Software y aplicaciones</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">Instalar o actualizar un programa, error al abrir una aplicación</p>
                    </div>
                </div>

                <div class="service-item">
                    <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center" style="background: oklch(97% 0.012 35);">
                        <svg style="color: oklch(52% 0.12 35); width:18px; height:18px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-stone-800 dark:text-stone-200 mb-1">Otro problema</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 leading-relaxed">Cualquier otra falla técnica — descríbela y la asignamos al área correcta</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════
         CTA FINAL
         ═══════════════════════════════════════════════ --}}
    <section class="bg-stone-950 border-t border-stone-900">
        <div class="mx-auto max-w-5xl px-6 lg:px-8 py-16 lg:py-20">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-8">
                <div>
                    <h2 class="font-display text-2xl sm:text-3xl font-light text-stone-50 mb-2 leading-snug" style="overflow-wrap: anywhere; min-width: 0;">
                        ¿Tienes un problema ahora mismo?
                    </h2>
                    <p class="text-stone-400 text-sm leading-relaxed" style="max-width: 40ch;">
                        Inicia sesión con tu cuenta de Confipetrol y abre una solicitud.
                        Menos de dos minutos. Sin llamadas. Sin correos.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary justify-center">
                            Ir a mis tickets
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary justify-center">
                            Pedir ayuda
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                        @if(config('services.azure.client_id'))
                            <a href="{{ route('auth.azure') }}"
                               class="inline-flex items-center justify-center gap-2 rounded-lg border border-stone-700 bg-stone-900 hover:bg-stone-800 px-6 py-3 text-sm font-medium text-stone-200 whitespace-nowrap transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-amber-500">
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
         FOOTER — Ft1 minimal
         ═══════════════════════════════════════════════ --}}
    <footer class="bg-stone-950 border-t border-stone-900/80">
        <div class="mx-auto max-w-5xl px-6 lg:px-8 py-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="h-5 w-auto opacity-40">
                <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-stone-700">
                    Helpdesk · Uso interno · &copy; {{ date('Y') }}
                </span>
            </div>
            <div class="flex items-center gap-5 font-mono text-[10px] tracking-[0.16em] uppercase text-stone-700">
                <a href="#beneficios"    class="hover:text-stone-400 transition-colors">Beneficios</a>
                <a href="#como-funciona" class="hover:text-stone-400 transition-colors">Cómo funciona</a>
                <a href="#servicios"     class="hover:text-stone-400 transition-colors">Servicios</a>
                <a href="{{ route('login') }}" class="hover:text-stone-400 transition-colors">Ingresar</a>
            </div>
        </div>
    </footer>

</main>
</body>
</html>
