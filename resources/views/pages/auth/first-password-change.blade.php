<x-layouts::auth title="Cambiá tu contraseña temporal">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <flux:heading size="lg" class="mb-1">Bienvenido al Helpdesk</flux:heading>
            <flux:text class="text-zinc-500">
                Tu cuenta se creó con una contraseña temporal (los primeros 8 dígitos de tu cédula).
                Por seguridad, cambiala antes de continuar.
            </flux:text>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.first-change.update') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="current_password"
                label="Contraseña temporal actual"
                type="password"
                required
                autofocus
                autocomplete="current-password"
                placeholder="8 primeros dígitos de tu cédula"
                icon="lock-closed"
                viewable
            />

            <flux:input
                name="password"
                label="Nueva contraseña"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Mínimo 8 caracteres, con letras y números"
                icon="key"
                viewable
            />

            <flux:input
                name="password_confirmation"
                label="Confirmá la nueva contraseña"
                type="password"
                required
                autocomplete="new-password"
                icon="key"
                viewable
            />

            <flux:button
                variant="primary"
                type="submit"
                class="w-full"
                icon="check-circle"
            >
                Guardar y entrar
            </flux:button>
        </form>

        <flux:text class="text-center text-xs text-zinc-400">
            No podés continuar hasta cambiarla — es por seguridad.
        </flux:text>
    </div>
</x-layouts::auth>
