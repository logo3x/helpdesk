<div class="space-y-6">
    {{-- Hero / saludo --}}
    <div class="rounded-xl border border-zinc-200 bg-gradient-to-br from-sky-50 to-white p-6 dark:border-zinc-700 dark:from-sky-950/30 dark:to-zinc-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-sky-500 text-base font-semibold text-white shadow">
                    {{ $user?->initials() }}
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
        <a href="{{ route('portal.tickets.index') }}?status=" wire:navigate class="block rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-sky-400 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-sky-500">
            <div class="flex items-center justify-between">
                <flux:icon name="inbox" class="size-5 text-sky-500" />
                <flux:text size="xs" class="text-zinc-400">Total</flux:text>
            </div>
            <div class="mt-2 text-2xl font-semibold">{{ $totalCount }}</div>
            <flux:text size="sm" class="text-zinc-500">Tickets totales</flux:text>
        </a>

        <a href="{{ route('portal.tickets.index') }}" wire:navigate class="block rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-amber-400 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-amber-500">
            <div class="flex items-center justify-between">
                <flux:icon name="bolt" class="size-5 text-amber-500" />
                <flux:text size="xs" class="text-zinc-400">Activos</flux:text>
            </div>
            <div class="mt-2 text-2xl font-semibold">{{ $openCount }}</div>
            <flux:text size="sm" class="text-zinc-500">En proceso</flux:text>
        </a>

        <a href="{{ route('portal.tickets.index') }}?status=pendiente_cliente" wire:navigate class="block rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-red-400 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-red-500">
            <div class="flex items-center justify-between">
                <flux:icon name="exclamation-circle" class="size-5 text-red-500" />
                <flux:text size="xs" class="text-zinc-400">Acción</flux:text>
            </div>
            <div class="mt-2 text-2xl font-semibold">{{ $waitingMyResponse }}</div>
            <flux:text size="sm" class="text-zinc-500">Esperan tu respuesta</flux:text>
        </a>

        <a href="{{ route('portal.tickets.index') }}?status=resuelto" wire:navigate class="block rounded-lg border border-zinc-200 bg-white p-4 transition hover:border-green-400 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-green-500">
            <div class="flex items-center justify-between">
                <flux:icon name="check-circle" class="size-5 text-green-500" />
                <flux:text size="xs" class="text-zinc-400">Cerrados</flux:text>
            </div>
            <div class="mt-2 text-2xl font-semibold">{{ $resolvedCount }}</div>
            <flux:text size="sm" class="text-zinc-500">Resueltos / cerrados</flux:text>
        </a>
    </div>

    {{-- Accesos rápidos --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <a href="{{ route('portal.tickets.create') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-sky-400 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-sky-500">
            <div class="mb-3 inline-flex size-10 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400">
                <flux:icon name="plus-circle" class="size-6" />
            </div>
            <flux:heading size="sm">Crear ticket</flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-500">¿Tienes un problema o necesitas ayuda? Crea una solicitud y un agente te atenderá.</flux:text>
        </a>

        <a href="{{ route('portal.chatbot') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-purple-400 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-purple-500">
            <div class="mb-3 inline-flex size-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-400">
                <flux:icon name="chat-bubble-left-right" class="size-6" />
            </div>
            <flux:heading size="sm">Asistente IA</flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-500">Haz preguntas en lenguaje natural. El asistente busca respuestas en la base de conocimiento.</flux:text>
        </a>

        <a href="{{ route('portal.kb.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-emerald-400 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-emerald-500">
            <div class="mb-3 inline-flex size-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                <flux:icon name="book-open" class="size-6" />
            </div>
            <flux:heading size="sm">Centro de ayuda</flux:heading>
            <flux:text size="sm" class="mt-1 text-zinc-500">Navega artículos publicados con guías, procedimientos y respuestas a las preguntas más comunes.</flux:text>
        </a>
    </div>

    {{-- Últimos tickets + KB destacados --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Mis últimos tickets (2/3) --}}
        <div class="lg:col-span-2 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="md">Mis últimos tickets</flux:heading>
                @if ($recent->count() > 0)
                    <flux:link :href="route('portal.tickets.index')" wire:navigate class="text-sm">Ver todos</flux:link>
                @endif
            </div>

            @forelse ($recent as $ticket)
                <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate
                   class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-2 transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <div class="mt-0.5 flex shrink-0">
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
                        <div class="flex items-center gap-2">
                            <flux:text size="xs" class="text-zinc-400">{{ $ticket->number }}</flux:text>
                            <span class="text-zinc-300 dark:text-zinc-600">·</span>
                            <flux:text size="xs" class="text-zinc-400">{{ $ticket->created_at->diffForHumans() }}</flux:text>
                        </div>
                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $ticket->subject }}</div>
                        <flux:text size="sm" class="text-zinc-500 truncate">
                            {{ $ticket->category?->name ?? 'Sin categoría' }}
                            @if ($ticket->assignee) · Atendido por {{ $ticket->assignee->name }} @endif
                        </flux:text>
                    </div>
                </a>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 py-8 text-center dark:border-zinc-600">
                    <flux:icon name="ticket" class="mx-auto mb-2 size-8 text-zinc-400" />
                    <flux:text class="text-zinc-500">Aún no has creado ningún ticket.</flux:text>
                    <flux:button :href="route('portal.tickets.create')" wire:navigate variant="primary" size="sm" icon="plus" class="mt-3">
                        Crear el primero
                    </flux:button>
                </div>
            @endforelse
        </div>

        {{-- KB destacados (1/3) --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="md">Lo más leído</flux:heading>
                @if ($featuredKb->count() > 0)
                    <flux:link :href="route('portal.kb.index')" wire:navigate class="text-sm">Ver todos</flux:link>
                @endif
            </div>

            @forelse ($featuredKb as $article)
                <a href="{{ route('portal.kb.show', $article->slug) }}" wire:navigate
                   class="-mx-2 mb-1 flex items-start gap-2 rounded-lg px-2 py-2 transition hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <flux:icon name="document-text" class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $article->title }}</div>
                        <flux:text size="xs" class="text-zinc-400">
                            {{ $article->category?->name ?? '' }}
                            @if ($article->views_count > 0) · {{ $article->views_count }} vistas @endif
                        </flux:text>
                    </div>
                </a>
            @empty
                <flux:text size="sm" class="text-zinc-500">Aún no hay artículos publicados.</flux:text>
            @endforelse
        </div>
    </div>
</div>
