<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.portal')] #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $identification = '';
    public string $position = '';
    public string $phone = '';
    public string $management_area = '';
    public string $field = '';
    public string $location_zone = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->identification = $user->identification ?? '';
        $this->position = $user->position ?? '';
        $this->phone = $user->phone ?? '';
        $this->management_area = $user->management_area ?? '';
        $this->field = $user->field ?? '';
        $this->location_zone = $user->location_zone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">Configuración de perfil</flux:heading>

    {{-- Callout de bienvenida post-ASL — borde izquierdo de acento, más compacto --}}
    @if(session('asl_just_accepted'))
        <div class="mb-6 flex gap-4 rounded-md border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-4">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Acuerdos aceptados correctamente.</p>
                <p class="mt-0.5 text-sm text-emerald-700 dark:text-emerald-400">Completa tus datos laborales para que el equipo de soporte pueda asignarte activos y darte el nivel de acceso correcto.</p>
            </div>
        </div>
    @endif

    <x-pages::settings.layout heading="Perfil" subheading="Actualiza tu nombre y correo electrónico">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">

            {{-- Avatar inicial + nombre de cuenta --}}
            <div class="flex items-center gap-4 pb-2">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 text-lg font-semibold select-none">
                    {{ mb_strtoupper(mb_substr($name ?: 'U', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $name }}</p>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $email }}</p>
                </div>
            </div>

            <flux:separator variant="subtle" />

            {{-- Datos de cuenta --}}
            <flux:input wire:model="name" label="Nombre" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" label="Correo electrónico" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div class="mt-3 flex items-start gap-2 rounded border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 px-3 py-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                        <flux:text class="text-xs text-amber-700 dark:text-amber-400">
                            Correo sin verificar.
                            <flux:link class="cursor-pointer underline" wire:click.prevent="resendVerificationNotification">
                                Reenviar verificación
                            </flux:link>
                        </flux:text>
                    </div>
                @endif
            </div>

            {{-- Separador visual con etiqueta de sección --}}
            <div class="flex items-center gap-3 pt-2">
                <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
                <span class="text-xs font-medium tracking-widest uppercase text-zinc-400 dark:text-zinc-500">Datos laborales</span>
                <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
            </div>

            <p class="text-xs text-zinc-500 dark:text-zinc-400 -mt-2">
                Estos datos se usan para la asignación de activos y el enrutamiento de tickets.
            </p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:input wire:model="identification" label="Cédula / Identificación" type="text" autocomplete="off" placeholder="Ej: 12345678" />
                <flux:input wire:model="position" label="Cargo" type="text" autocomplete="off" placeholder="Ej: Técnico de campo" />
                <flux:input wire:model="phone" label="Teléfono" type="tel" autocomplete="tel" placeholder="Ej: 3001234567" />
                <flux:input wire:model="management_area" label="Gerencia / Área" type="text" autocomplete="off" placeholder="Ej: HSEQ, Operaciones" />
                <flux:input wire:model="field" label="Campo" type="text" autocomplete="off" placeholder="Ej: PORE, SAN MARTIN" />
                <flux:input wire:model="location_zone" label="Ubicación / Zona" type="text" autocomplete="off" placeholder="Ej: ZONA 4, Bodega central" />
            </div>

            <div class="flex items-center gap-4 pt-1">
                <flux:button variant="primary" type="submit" data-test="update-profile-button">
                    Guardar cambios
                </flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
