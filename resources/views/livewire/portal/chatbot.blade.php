<div class="mx-auto max-w-2xl">
    <flux:heading size="xl" class="mb-6">Asistente virtual</flux:heading>

    {{-- Chat messages --}}
    <div class="mb-4 h-[28rem] overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900"
         id="chat-container"
         x-data
         x-on:chat-updated.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
        <div class="space-y-3">
            @foreach ($history as $msg)
                <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[80%] rounded-lg px-4 py-2 text-sm {{ $msg['role'] === 'user'
                        ? 'bg-sky-500 text-white dark:bg-sky-600'
                        : 'bg-white text-zinc-800 shadow-sm dark:bg-zinc-800 dark:text-zinc-200' }}">
                        {!! str($msg['content'])->markdown()->toHtmlString() !!}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Input --}}
    <form wire:submit="send" class="flex gap-2">
        <flux:input
            wire:model="message"
            placeholder="Escribe tu consulta..."
            autofocus
            class="flex-1"
        />
        <flux:button type="submit" variant="primary" icon="paper-airplane">
            Enviar
        </flux:button>
    </form>

    <flux:text size="sm" class="mt-2 text-zinc-400">
        Escribe <strong>"crear ticket"</strong> para hablar con un agente. Usa <strong>"escalar: [resumen]"</strong> para crear un ticket directamente.
    </flux:text>
</div>
