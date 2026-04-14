<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- Portal header — clean top‐bar for end users --}}
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:brand href="{{ route('portal.tickets.index') }}" wire:navigate name="{{ config('app.name') }}" class="max-lg:hidden" />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="plus-circle" :href="route('portal.tickets.create')" :current="request()->routeIs('portal.tickets.create')" wire:navigate>
                    Crear ticket
                </flux:navbar.item>
                <flux:navbar.item icon="inbox" :href="route('portal.tickets.index')" :current="request()->routeIs('portal.tickets.index')" wire:navigate>
                    Mis tickets
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <div class="px-2 py-1.5">
                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        Configuración
                    </flux:menu.item>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{-- Mobile nav --}}
        <flux:header class="lg:hidden border-b border-zinc-200 dark:border-zinc-700">
            <flux:navbar>
                <flux:navbar.item icon="plus-circle" :href="route('portal.tickets.create')" :current="request()->routeIs('portal.tickets.create')" wire:navigate>
                    Crear
                </flux:navbar.item>
                <flux:navbar.item icon="inbox" :href="route('portal.tickets.index')" :current="request()->routeIs('portal.tickets.index')" wire:navigate>
                    Mis tickets
                </flux:navbar.item>
            </flux:navbar>
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @vite('resources/js/inventory-collector.js')
    </body>
</html>
