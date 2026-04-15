{{--
    Helper Blade component inline: reusable label with tooltip (?) icon.
    Uses Flux's <flux:tooltip> + <flux:icon.question-mark-circle>.
--}}
@php
    $labelWithTooltip = function (string $text, string $tip) {
        return "<span class='inline-flex items-center gap-1'>"
            . htmlspecialchars($text)
            . "</span>";
    };
@endphp

<div class="mx-auto max-w-2xl">
    <flux:heading size="xl" class="mb-6">Crear nuevo ticket</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label class="flex items-center gap-1.5">
                Asunto
                <flux:tooltip content="Un resumen corto del problema, idealmente entre 5 y 100 caracteres. Ej: 'No puedo enviar correos'.">
                    <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                </flux:tooltip>
            </flux:label>
            <flux:input
                wire:model="subject"
                placeholder="Describe brevemente tu problema"
                required
            />
            <flux:error name="subject" />
        </flux:field>

        <flux:field>
            <flux:label class="flex items-center gap-1.5">
                Descripción
                <flux:tooltip content="Explica el problema con detalle: cuándo empezó, qué estabas haciendo, qué mensaje de error viste y qué has intentado. A mayor detalle, más rápido te ayudamos.">
                    <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                </flux:tooltip>
            </flux:label>
            <flux:textarea
                wire:model="description"
                placeholder="Describe con detalle qué ocurre, cuándo empezó y qué has intentado..."
                rows="5"
                required
            />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label class="flex items-center gap-1.5">
                Categoría
                <flux:tooltip content="Selecciona el tipo de solicitud para que el ticket llegue al equipo correcto (ej: Hardware, Correo, Nómina).">
                    <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                </flux:tooltip>
            </flux:label>
            <flux:select wire:model="category_id" placeholder="Selecciona una categoría...">
                @foreach ($categories as $cat)
                    <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="category_id" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field>
                <flux:label class="flex items-center gap-1.5">
                    Impacto
                    <flux:tooltip content="Alcance del problema: Bajo (solo a ti), Medio (a tu equipo o área), Alto (a toda la empresa o una operación crítica).">
                        <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                    </flux:tooltip>
                </flux:label>
                <flux:select wire:model.live="impact">
                    <flux:select.option value="bajo">Bajo</flux:select.option>
                    <flux:select.option value="medio">Medio</flux:select.option>
                    <flux:select.option value="alto">Alto</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label class="flex items-center gap-1.5">
                    Urgencia
                    <flux:tooltip content="Qué tan rápido necesitas solución: Baja (cuando se pueda), Media (esta semana), Alta (hoy mismo o bloquea trabajo).">
                        <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                    </flux:tooltip>
                </flux:label>
                <flux:select wire:model.live="urgency">
                    <flux:select.option value="baja">Baja</flux:select.option>
                    <flux:select.option value="media">Media</flux:select.option>
                    <flux:select.option value="alta">Alta</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label class="flex items-center gap-1.5">
                    Prioridad calculada
                    <flux:tooltip content="Se calcula automáticamente desde Impacto × Urgencia según la matriz ITIL. Determina el tiempo de respuesta (SLA).">
                        <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                    </flux:tooltip>
                </flux:label>
                <flux:badge size="lg" color="zinc" class="mt-1">
                    {{ $this->computedPriority }}
                </flux:badge>
            </flux:field>
        </div>

        <flux:field>
            <flux:label class="flex items-center gap-1.5">
                Adjuntos
                <flux:tooltip content="Opcional. Adjunta capturas de pantalla, documentos o logs que ayuden a entender el problema. Máximo 5 archivos de 10 MB cada uno.">
                    <flux:icon.question-mark-circle class="size-4 text-zinc-400 cursor-help" />
                </flux:tooltip>
            </flux:label>
            <input
                type="file"
                wire:model="attachments"
                multiple
                class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600"
            />
            @error('attachments.*') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
            <div wire:loading wire:target="attachments" class="mt-1">
                <flux:text size="sm" class="text-zinc-400">Subiendo archivos...</flux:text>
            </div>
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-4">
            <flux:button :href="route('portal.tickets.index')" variant="ghost" wire:navigate>
                Cancelar
            </flux:button>
            <flux:button type="submit" variant="primary">
                Enviar ticket
            </flux:button>
        </div>
    </form>
</div>
