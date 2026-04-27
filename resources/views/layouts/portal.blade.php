<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{-- Portal header — clean top‐bar for end users --}}
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:brand href="{{ route('portal.tickets.index') }}" wire:navigate class="max-lg:hidden">
                <x-slot name="logo">
                    <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol" class="block h-6 w-auto max-h-full object-contain dark:hidden" />
                    <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="hidden h-6 w-auto max-h-full object-contain dark:block" />
                </x-slot>
            </flux:brand>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="home" :href="route('portal.home')" :current="request()->routeIs('portal.home')" wire:navigate>
                    Inicio
                </flux:navbar.item>
                <flux:navbar.item icon="plus-circle" :href="route('portal.tickets.create')" :current="request()->routeIs('portal.tickets.create')" wire:navigate>
                    Crear ticket
                </flux:navbar.item>
                <flux:navbar.item icon="inbox" :href="route('portal.tickets.index')" :current="request()->routeIs('portal.tickets.index')" wire:navigate>
                    Mis tickets
                </flux:navbar.item>
                <flux:navbar.item icon="chat-bubble-left-right" :href="route('portal.chatbot')" :current="request()->routeIs('portal.chatbot')" wire:navigate>
                    Asistente
                </flux:navbar.item>
                <flux:navbar.item icon="book-open" :href="route('portal.kb.index')" :current="request()->routeIs('portal.kb.*')" wire:navigate>
                    Centro de ayuda
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            {{-- Campanita de notificaciones (lee de tabla `notifications`,
                 polling 30s, mismo shape Filament que /soporte y /admin). --}}
            <livewire:portal.notifications-bell />

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
                <flux:navbar.item icon="home" :href="route('portal.home')" :current="request()->routeIs('portal.home')" wire:navigate>
                    Inicio
                </flux:navbar.item>
                <flux:navbar.item icon="plus-circle" :href="route('portal.tickets.create')" :current="request()->routeIs('portal.tickets.create')" wire:navigate>
                    Crear
                </flux:navbar.item>
                <flux:navbar.item icon="inbox" :href="route('portal.tickets.index')" :current="request()->routeIs('portal.tickets.index')" wire:navigate>
                    Mis tickets
                </flux:navbar.item>
                <flux:navbar.item icon="book-open" :href="route('portal.kb.index')" :current="request()->routeIs('portal.kb.*')" wire:navigate>
                    Ayuda
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
