<div class="mx-auto max-w-lg py-10 px-4">
    <style>
        .star-btn { transition: transform .1s ease; }
        .star-btn:hover { transform: scale(1.15); }
    </style>

    @if ($survey->isPending())
        <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            {{-- Header --}}
            <div class="border-b border-zinc-100 bg-gradient-to-br from-amber-50 to-orange-50 px-6 py-5 dark:border-zinc-800 dark:from-amber-950/30 dark:to-orange-950/20">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50">
                        <flux:icon name="wrench-screwdriver" class="size-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <flux:heading size="lg">Encuesta de mantenimiento</flux:heading>
                        @php
                            $label = $survey->asset->hostname ?? $survey->asset->asset_tag ?? "Activo #{$survey->asset->id}";
                        @endphp
                        <flux:text size="sm" class="mt-0.5 text-zinc-500">
                            {{ $label }} · {{ $survey->asset->manufacturer }} {{ $survey->asset->model }}
                        </flux:text>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-6">
                <div>
                    <flux:text class="mb-3 font-medium text-zinc-700 dark:text-zinc-300">
                        ¿Cómo calificarías el mantenimiento realizado a tu equipo?
                    </flux:text>

                    <div class="flex gap-2" x-data="{ hovered: 0 }">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    class="star-btn text-3xl focus:outline-none"
                                    x-on:mouseenter="hovered = {{ $i }}"
                                    x-on:mouseleave="hovered = 0"
                                    wire:click="$set('rating', {{ $i }})">
                                <span x-bind:class="(hovered >= {{ $i }} || {{ $i }} <= $wire.rating) ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600'">★</span>
                            </button>
                        @endfor
                        @if ($rating > 0)
                            <flux:text size="sm" class="ml-2 self-center text-zinc-500">
                                {{ ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'][$rating] }}
                            </flux:text>
                        @endif
                    </div>
                    @error('rating')
                        <flux:text size="sm" class="mt-1 text-red-500">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:label>Comentario opcional</flux:label>
                    <flux:textarea wire:model="comment" rows="3"
                                  placeholder="¿El equipo quedó funcionando correctamente? ¿Algún comentario?" />
                </div>

                <flux:button wire:click="submit" variant="primary" class="w-full" icon="check">
                    Enviar calificación
                </flux:button>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center dark:border-emerald-800/60 dark:bg-emerald-950/30">
            <flux:icon name="check-circle" class="mx-auto mb-3 size-12 text-emerald-500" />
            <flux:heading size="lg">¡Gracias por responder!</flux:heading>
            <flux:text class="mt-2 text-zinc-500">Esta encuesta ya fue completada.</flux:text>
            <flux:button :href="route('portal.assets.index')" wire:navigate variant="primary" class="mt-5">
                Ver mis activos
            </flux:button>
        </div>
    @endif
</div>
