<div class="mx-auto max-w-2xl">
    <flux:heading size="xl" class="mb-6">Crear nuevo ticket</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input
            wire:model="subject"
            label="Asunto"
            placeholder="Describe brevemente tu problema"
            required
        />

        <flux:textarea
            wire:model="description"
            label="Descripción"
            placeholder="Describe con detalle qué ocurre, cuándo empezó y qué has intentado..."
            rows="5"
            required
        />

        <flux:select wire:model="category_id" label="Categoría" placeholder="Selecciona una categoría...">
            @foreach ($categories as $cat)
                <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:select wire:model.live="impact" label="Impacto">
                <flux:select.option value="bajo">Bajo</flux:select.option>
                <flux:select.option value="medio">Medio</flux:select.option>
                <flux:select.option value="alto">Alto</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="urgency" label="Urgencia">
                <flux:select.option value="baja">Baja</flux:select.option>
                <flux:select.option value="media">Media</flux:select.option>
                <flux:select.option value="alta">Alta</flux:select.option>
            </flux:select>

            <div>
                <flux:text class="mb-1 text-sm font-medium">Prioridad calculada</flux:text>
                <flux:badge size="lg" color="zinc" class="mt-1">
                    {{ $this->computedPriority }}
                </flux:badge>
            </div>
        </div>

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
