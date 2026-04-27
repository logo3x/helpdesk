<x-layouts::auth.card title="Iniciar sesión">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <flux:heading size="lg" class="mb-1">Helpdesk Confipetrol</flux:heading>
            <flux:text class="text-zinc-500">Ingresa con tu cuenta corporativa para crear tickets, atender solicitudes o consultar la base de conocimiento.</flux:text>
        </div>

        {{-- Mensaje de sesión (status) --}}
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                label="Correo electrónico"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="correo@confipetrol.com"
                icon="envelope"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="Contraseña"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Tu contraseña"
                    icon="lock-closed"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-xs end-0" :href="route('password.request')" wire:navigate>
                        ¿Olvidaste tu contraseña?
                    </flux:link>
                @endif
            </div>

            <flux:checkbox name="remember" label="Recordarme en este equipo" :checked="old('remember')" />

            <flux:button
                variant="primary"
                type="submit"
                class="w-full"
                icon="arrow-right-end-on-rectangle"
                data-test="login-button"
            >
                Iniciar sesión
            </flux:button>
        </form>

        {{-- SSO Azure AD (si está configurado) --}}
        @if (config('services.azure.client_id'))
            <div class="relative my-1">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t border-zinc-200 dark:border-zinc-700"></span>
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="bg-white px-2 text-zinc-400 dark:bg-stone-950">o continúa con</span>
                </div>
            </div>

            <flux:button
                :href="route('auth.azure')"
                variant="outline"
                class="w-full"
                icon="building-office-2"
            >
                Cuenta corporativa Azure AD
            </flux:button>
        @endif

        <flux:text class="text-center text-xs text-zinc-400">
            Al ingresar aceptas las políticas de uso del Helpdesk Confipetrol.
        </flux:text>
    </div>
</x-layouts::auth.card>
