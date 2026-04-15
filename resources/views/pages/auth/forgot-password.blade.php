<x-layouts::auth title="Olvidé mi contraseña">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Olvidé mi contraseña" description="Ingresa tu correo electrónico para recibir un enlace de restablecimiento" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                label="Correo electrónico"
                type="email"
                required
                autofocus
                placeholder="correo@confipetrol.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                Enviar enlace de restablecimiento
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>O vuelve a</span>
            <flux:link :href="route('login')" wire:navigate>iniciar sesión</flux:link>
        </div>
    </div>
</x-layouts::auth>
