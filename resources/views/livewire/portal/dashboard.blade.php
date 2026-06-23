<div class="space-y-6"
     x-data="{}"
     x-init="
        document.querySelectorAll('.dash-stat').forEach((el, i) => {
            el.style.animationDelay = (i * 60) + 'ms';
        });
        document.querySelectorAll('.dash-quick').forEach((el, i) => {
            el.style.animationDelay = (i * 70) + 'ms';
        });
     ">

    <style>
        @keyframes dashFadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes dashHeroIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .dash-hero  { animation: dashHeroIn  .3s ease both; }
        .dash-stat  { animation: dashFadeUp  .3s ease both; opacity: 0; }
        .dash-quick { animation: dashFadeUp  .35s ease both; opacity: 0; }
        .dash-panel { animation: dashFadeUp  .4s ease .15s both; }
    </style>

    {{-- Hero / saludo --}}
    <div class="dash-hero overflow-hidden rounded-xl border border-sky-200/60 bg-gradient-to-br from-sky-50 via-white to-indigo-50 p-6 shadow-sm dark:border-sky-900/40 dark:from-sky-950/30 dark:via-zinc-900 dark:to-indigo-950/20">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="relative flex size-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-sky-600 text-base font-bold text-white shadow-md">
                    {{ $user?->initials() }}
                    <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-zinc-900"></span>
                </div>
                <div>
                    <flux:heading size="xl">
                        @php
                            $hour = now()->hour;
                            $greeting = match (true) {
                                $hour < 12 => 'Buenos días',
                                $hour < 19 => 'Buenas tardes',
                                default => 'Buenas noches',
                            };
                        @endphp
                        {{ $greeting }}, {{ explode(' ', (string) $user?->name)[0] ?? '' }}
                    </flux:heading>
                    <flux:text class="mt-0.5 text-zinc-500">
                        Bienvenido al Helpdesk Confipetrol. ¿En qué te ayudamos hoy?
                    </flux:text>
                </div>
            </div>
            <flux:button :href="route('portal.tickets.create')" wire:navigate variant="primary" icon="plus">
                Crear ticket
            </flux:button>
        </div>
    </div>

    {{-- Stats personales --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <a href="{{ route('portal.tickets.index') }}?status=" wire:navigate
           class="dash-stat group block overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80 dark:hover:border-sky-600">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-950/50">
                <flux:icon name="inbox" class="size-4.5 text-sky-500" />
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $totalCount }}</div>
            <flux:text size="sm" class="mt-0.5 text-zinc-500">Tickets totales</flux:text>
        </a>

        <a href="{{ route('portal.tickets.index') }}" wire:navigate
           class="dash-stat group block overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80 dark:hover:border-amber-600">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/50">
                <flux:icon name="bolt" class="size-4.5 text-amber-500" />
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $openCount }}</div>
            <flux:text size="sm" class="mt-0.5 text-zinc-500">En proceso</flux:text>
        </a>

        <a href="{{ route('portal.tickets.index') }}?status=pendiente_cliente" wire:navigate
           class="dash-stat group block overflow-hidden rounded-xl border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md p-4 shadow-sm
               {{ $waitingMyResponse > 0
                   ? 'border-red-200 bg-red-50 dark:border-red-800/60 dark:bg-red-950/30 hover:border-red-300'
                   : 'border-zinc-200/80 bg-white dark:border-zinc-700/80 dark:bg-zinc-900/80 hover:border-zinc-300' }}">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg {{ $waitingMyResponse > 0 ? 'bg-red-100 dark:bg-red-900/50' : 'bg-zinc-50 dark:bg-zinc-800' }}
                {{ $waitingMyResponse > 0 ? 'animate-pulse' : '' }}">
                <flux:icon name="exclamation-circle" class="size-4.5 {{ $waitingMyResponse > 0 ? 'text-red-500' : 'text-zinc-400' }}" />
            </div>
            <div class="text-2xl font-bold {{ $waitingMyResponse > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">{{ $waitingMyResponse }}</div>
            <flux:text size="sm" class="mt-0.5 {{ $waitingMyResponse > 0 ? 'text-red-400' : 'text-zinc-500' }}">Esperan tu respuesta</flux:text>
        </a>

        <a href="{{ route('portal.tickets.index') }}?status=resuelto" wire:navigate
           class="dash-stat group block overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80 dark:hover:border-emerald-600">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/50">
                <flux:icon name="check-circle" class="size-4.5 text-emerald-500" />
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $resolvedCount }}</div>
            <flux:text size="sm" class="mt-0.5 text-zinc-500">Resueltos / cerrados</flux:text>
        </a>
    </div>

    {{-- Asistente IA destacado --}}
    <form method="GET" action="{{ route('portal.chatbot') }}"
          class="dash-panel overflow-hidden rounded-xl border border-purple-200/80 bg-gradient-to-br from-purple-50 via-white to-sky-50 p-5 shadow-sm dark:border-purple-800/60 dark:from-purple-950/40 dark:via-zinc-900 dark:to-sky-950/30">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex shrink-0">
                @if (file_exists(public_path('images/robotconfipetrol.png')))
                    <img src="{{ asset('images/robotconfipetrol.png') }}" alt="Asistente Confipetrol"
                         class="size-20 rounded-full bg-white object-contain ring-2 ring-purple-200 shadow-sm dark:bg-zinc-800 dark:ring-purple-700" />
                @else
                    <div class="flex size-20 items-center justify-center rounded-full bg-gradient-to-br from-purple-100 to-indigo-100 text-purple-600 ring-2 ring-purple-200 shadow-sm dark:from-purple-950 dark:to-indigo-950 dark:text-purple-300 dark:ring-purple-700">
                        <flux:icon name="cpu-chip" class="size-10" />
                    </div>
                @endif
            </div>
            <div class="flex-1">
                <flux:heading size="md">
                    Hola {{ explode(' ', (string) $user?->name)[0] ?? '' }}, soy tu asistente Confipetrol
                </flux:heading>
                <flux:text size="sm" class="mt-1 text-zinc-500">
                    Pregúntame sobre reset de contraseña, VPN, impresoras, software y más. Si no encuentro respuesta, te ayudo a crear un ticket.
                </flux:text>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input type="text" name="q" placeholder="Escribe tu pregunta..."
                           class="flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm transition-shadow focus:border-purple-400 focus:outline-none focus:ring-2 focus:ring-purple-200 dark:border-zinc-700 dark:bg-zinc-900 dark:focus:ring-purple-900/50" />
                    <flux:button type="submit" variant="primary" icon="sparkles">Preguntar</flux:button>
                </div>
            </div>
        </div>
    </form>

    {{-- Accesos rápidos --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <a href="{{ route('portal.tickets.create') }}" wire:navigate
           class="dash-quick group overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 transition-colors duration-150 group-hover:bg-sky-100 dark:bg-sky-950/50 dark:group-hover:bg-sky-900/50">
                <flux:icon name="plus-circle" class="size-5 text-sky-600" />
            </div>
            <flux:heading size="sm">Crear ticket</flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-500">¿Tienes un problema o necesitas ayuda? Crea una solicitud y un agente te atenderá.</flux:text>
        </a>

        <a href="{{ route('portal.assets.index') }}" wire:navigate
           class="dash-quick group overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 transition-colors duration-150 group-hover:bg-amber-100 dark:bg-amber-950/50 dark:group-hover:bg-amber-900/50">
                <flux:icon name="computer-desktop" class="size-5 text-amber-600" />
            </div>
            <flux:heading size="sm">Mis activos</flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-500">Consulta los equipos asignados a tu custodia y confirma la recepción de actas pendientes.</flux:text>
        </a>

        <a href="{{ route('portal.kb.index') }}" wire:navigate
           class="dash-quick group overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 transition-colors duration-150 group-hover:bg-emerald-100 dark:bg-emerald-950/50 dark:group-hover:bg-emerald-900/50">
                <flux:icon name="book-open" class="size-5 text-emerald-600" />
            </div>
            <flux:heading size="sm">Centro de ayuda</flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-500">Navega artículos publicados con guías, procedimientos y respuestas a las preguntas más comunes.</flux:text>
        </a>
    </div>

    {{-- Últimos tickets + KB destacados --}}
    <div class="dash-panel grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Mis últimos tickets (2/3) --}}
        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3.5 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="clock" class="size-4 text-zinc-400" />
                    <flux:heading size="sm">Mis últimos tickets</flux:heading>
                </div>
                @if ($recent->count() > 0)
                    <flux:link :href="route('portal.tickets.index')" wire:navigate class="text-xs text-zinc-400 hover:text-sky-500">Ver todos</flux:link>
                @endif
            </div>
            <div class="p-3">
                @forelse ($recent as $ticket)
                    <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate
                       class="group flex items-start gap-3 rounded-lg px-3 py-2.5 transition-all duration-150 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                        <div class="mt-0.5 shrink-0">
                            <flux:badge size="sm" :color="match($ticket->status->value) {
                                'nuevo' => 'sky',
                                'asignado' => 'blue',
                                'en_progreso' => 'amber',
                                'pendiente_cliente' => 'red',
                                'resuelto' => 'green',
                                'cerrado' => 'zinc',
                                'reabierto' => 'red',
                                default => 'zinc',
                            }">{{ $ticket->status->getLabel() }}</flux:badge>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 text-xs text-zinc-400">
                                <span class="font-mono">{{ $ticket->number }}</span>
                                <span>·</span>
                                <span>{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="mt-0.5 truncate text-sm font-medium text-zinc-800 transition-colors group-hover:text-sky-600 dark:text-zinc-100 dark:group-hover:text-sky-400">
                                {{ $ticket->subject }}
                            </div>
                        </div>
                        <flux:icon name="chevron-right" class="mt-1 size-3.5 shrink-0 text-zinc-300 transition-transform group-hover:translate-x-0.5 group-hover:text-sky-400" />
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-zinc-200 py-8 text-center dark:border-zinc-700">
                        <flux:icon name="ticket" class="mx-auto mb-2 size-8 text-zinc-300" />
                        <flux:text size="sm" class="text-zinc-400">Aún no has creado ningún ticket.</flux:text>
                        <flux:button :href="route('portal.tickets.create')" wire:navigate variant="primary" size="sm" icon="plus" class="mt-3">
                            Crear el primero
                        </flux:button>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KB destacados (1/3) --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3.5 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon name="fire" class="size-4 text-orange-400" />
                    <flux:heading size="sm">Lo más leído</flux:heading>
                </div>
                @if ($featuredKb->count() > 0)
                    <flux:link :href="route('portal.kb.index')" wire:navigate class="text-xs text-zinc-400 hover:text-emerald-500">Ver todos</flux:link>
                @endif
            </div>
            <div class="p-3">
                @forelse ($featuredKb as $article)
                    <a href="{{ route('portal.kb.show', $article->slug) }}" wire:navigate
                       class="group flex items-start gap-2.5 rounded-lg px-3 py-2.5 transition-all duration-150 hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                        <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-emerald-50 dark:bg-emerald-950/50">
                            <flux:icon name="document-text" class="size-3.5 text-emerald-500" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-zinc-800 transition-colors group-hover:text-emerald-600 dark:text-zinc-100 dark:group-hover:text-emerald-400">
                                {{ $article->title }}
                            </div>
                            <div class="mt-0.5 flex items-center gap-1 text-xs text-zinc-400">
                                @if ($article->category)
                                    <span>{{ $article->category->name }}</span>
                                @endif
                                @if ($article->views_count > 0)
                                    <span>·</span>
                                    <span>{{ $article->views_count }} vistas</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="py-6 text-center">
                        <flux:text size="sm" class="text-zinc-400">Aún no hay artículos publicados.</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
