<div class="min-h-screen bg-gradient-to-br from-sky-50 via-white to-indigo-50 dark:from-zinc-950 dark:via-zinc-900 dark:to-indigo-950/20 py-10 px-4">
    <div class="mx-auto max-w-2xl">

        @if ($survey->isPending())
            {{-- Logo / branding --}}
            <div class="mb-8 text-center">
                <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-sky-600 shadow-lg shadow-sky-200 dark:shadow-sky-900/40 mb-4">
                    <flux:icon name="star" class="size-8 text-white" />
                </div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
                    ¡Tu opinión es muy importante!
                </flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                    Ticket <span class="font-semibold text-sky-600">{{ $survey->ticket->number }}</span>
                    — {{ Str::limit($survey->ticket->subject, 60) }}
                </flux:text>
            </div>

            <div class="rounded-2xl bg-white shadow-xl shadow-zinc-200/60 dark:bg-zinc-900 dark:shadow-zinc-950/60 overflow-hidden">

                {{-- Instrucción --}}
                <div class="bg-gradient-to-r from-sky-600 to-indigo-600 px-6 py-4 text-white">
                    <p class="text-sm font-medium opacity-90">
                        Califica del 1 al 5, donde ★ es muy insatisfecho y ★★★★★ es muy satisfecho.
                    </p>
                </div>

                <div class="p-6 space-y-1">
                    @foreach(\App\Models\SatisfactionSurvey::DIMENSIONS as $field => $label)
                        <div
                            x-data="{ hover: 0, value: $wire.entangle('{{ $field }}').live }"
                            class="flex items-center justify-between gap-4 rounded-xl px-4 py-3 transition-colors hover:bg-sky-50 dark:hover:bg-zinc-700/60"
                        >
                            <span class="flex-1 text-sm font-medium text-zinc-700 dark:text-zinc-200 leading-tight group-hover:text-zinc-900 dark:group-hover:text-white">
                                {{ $label }}
                            </span>
                            <div class="flex items-center gap-1 shrink-0">
                                @foreach([1,2,3,4,5] as $n)
                                    <button
                                        type="button"
                                        @mouseenter="hover = {{ $n }}"
                                        @mouseleave="hover = 0"
                                        @click="value = {{ $n }}"
                                        class="text-2xl leading-none transition-transform duration-75 focus:outline-none"
                                        :class="{
                                            'text-amber-400 scale-110': (hover >= {{ $n }} || (hover === 0 && value >= {{ $n }})),
                                            'text-zinc-300 dark:text-zinc-500': !(hover >= {{ $n }} || (hover === 0 && value >= {{ $n }}))
                                        }"
                                        aria-label="Calificación {{ $n }}"
                                    >★</button>
                                @endforeach
                            </div>
                        </div>
                        @error($field)
                            <p class="px-4 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>

                <div class="border-t border-zinc-100 dark:border-zinc-800 px-6 pb-6 pt-5 space-y-4">
                    {{-- Comentario --}}
                    <div>
                        <flux:label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            ¿Quieres compartir algo que nos permita mejorar? <span class="font-normal text-zinc-400">(opcional)</span>
                        </flux:label>
                        <flux:textarea
                            wire:model="comment"
                            rows="3"
                            placeholder="Tu comentario es muy valioso para nosotros..."
                            class="resize-none"
                        />
                    </div>

                    <flux:button
                        wire:click="submit"
                        wire:loading.attr="disabled"
                        variant="primary"
                        class="w-full"
                        icon="check"
                    >
                        <span wire:loading.remove wire:target="submit">Enviar calificación</span>
                        <span wire:loading wire:target="submit">Enviando...</span>
                    </flux:button>
                </div>
            </div>

        @else
            <div class="text-center">
                <div class="inline-flex items-center justify-center h-20 w-20 rounded-full bg-emerald-100 dark:bg-emerald-900/40 mb-5">
                    <flux:icon name="check-circle" class="size-10 text-emerald-500" />
                </div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
                    ¡Gracias por tu respuesta!
                </flux:heading>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    Esta encuesta ya fue completada. Tu opinión nos ayuda a mejorar el servicio.
                </flux:text>
                <div class="mt-6">
                    <flux:button :href="route('portal.tickets.index')" wire:navigate variant="primary">
                        Ver mis tickets
                    </flux:button>
                </div>
            </div>
        @endif

    </div>
</div>
