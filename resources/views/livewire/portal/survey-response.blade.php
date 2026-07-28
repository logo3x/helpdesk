<div class="mx-auto max-w-2xl py-10 px-4">

    @if ($survey->isPending())
        <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            {{-- Header --}}
            <div class="border-b border-zinc-100 bg-gradient-to-br from-sky-50 to-indigo-50 px-6 py-5 dark:border-zinc-800 dark:from-sky-950/30 dark:to-indigo-950/20">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-900/50">
                        <flux:icon name="star" class="size-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-center font-bold text-sky-800 dark:text-sky-200">
                            ¡TU OPINIÓN ES MUY IMPORTANTE PARA NOSOTROS!
                        </flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-600 dark:text-zinc-400">
                            Ticket <strong>{{ $survey->ticket->number }}</strong> — {{ $survey->ticket->subject }}
                        </flux:text>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    En una escala de <strong>1</strong> a <strong>5</strong>, donde 1 es <em>muy insatisfecho</em> y 5 <em>muy satisfecho</em>:<br>
                    ¿Qué tan satisfecho estás con tu interacción con el soporte? Respecto a:
                </flux:text>

                {{-- Tabla de dimensiones --}}
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800">
                                <th class="py-3 pl-4 pr-2 text-left font-medium text-zinc-600 dark:text-zinc-300 w-full"></th>
                                @foreach([1,2,3,4,5] as $n)
                                    <th class="px-4 py-3 text-center font-bold text-zinc-700 dark:text-zinc-200 min-w-[48px]">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-sky-600 text-white text-sm font-bold">{{ $n }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach(\App\Models\SatisfactionSurvey::DIMENSIONS as $field => $label)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors" x-data>
                                    <td class="py-3 pl-4 pr-2 text-zinc-700 dark:text-zinc-300">{{ $label }}</td>
                                    @foreach([1,2,3,4,5] as $n)
                                        <td class="px-4 py-3 text-center">
                                            <label class="cursor-pointer">
                                                <input type="radio"
                                                       wire:model.live="{{ $field }}"
                                                       value="{{ $n }}"
                                                       class="h-4 w-4 accent-sky-600 cursor-pointer" />
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Errores de validación por dimensión --}}
                @foreach(array_keys(\App\Models\SatisfactionSurvey::DIMENSIONS) as $field)
                    @error($field)
                        <flux:text size="sm" class="text-red-500">{{ $message }}</flux:text>
                    @enderror
                @endforeach

                {{-- Comentario libre --}}
                <div>
                    <flux:label>
                        ¿Quieres compartirnos algo que nos permita mejorar la atención que recibes?
                    </flux:label>
                    <flux:textarea wire:model="comment" rows="4"
                                  placeholder="Tu comentario es opcional pero muy valioso para nosotros..." />
                </div>

                <flux:button wire:click="submit" wire:loading.attr="disabled" variant="primary" class="w-full" icon="check">
                    <span wire:loading.remove>ENVIAR</span>
                    <span wire:loading>Enviando...</span>
                </flux:button>
            </div>
        </div>

    @else
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center dark:border-emerald-800/60 dark:bg-emerald-950/30">
            <flux:icon name="check-circle" class="mx-auto mb-3 size-12 text-emerald-500" />
            <flux:heading size="lg">¡Gracias por responder!</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Esta encuesta ya fue completada. Tu opinión nos ayuda a mejorar.</flux:text>
            <flux:button :href="route('portal.tickets.index')" wire:navigate variant="primary" class="mt-5">
                Ver mis tickets
            </flux:button>
        </div>
    @endif
</div>
