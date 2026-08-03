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

    @if(session('asl_just_accepted'))
        <flux:callout variant="success" icon="check-circle" class="mb-6">
            <flux:callout.heading>¡Bienvenido! Acuerdos aceptados correctamente.</flux:callout.heading>
            <flux:callout.text>Por favor completa tus datos laborales para que el equipo de soporte pueda asignarte activos correctamente.</flux:callout.text>
        </flux:callout>
    @endif

    <x-pages::settings.layout heading="Perfil" subheading="Actualiza tu nombre y correo electrónico">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" label="Nombre" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" label="Correo electrónico" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            Tu correo electrónico no ha sido verificado.

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                Haz clic aquí para reenviar el correo de verificación.
                            </flux:link>
                        </flux:text>
                    </div>
                @endif
            </div>

            <flux:separator />

            <flux:heading size="sm" class="text-zinc-600">Datos laborales</flux:heading>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <flux:input wire:model="identification" label="Cédula / Identificación" type="text" autocomplete="off" placeholder="Ej: 12345678" />
                <flux:input wire:model="position" label="Cargo" type="text" autocomplete="off" placeholder="Ej: Técnico de campo" />
                <flux:input wire:model="phone" label="Teléfono" type="tel" autocomplete="tel" placeholder="Ej: 3001234567" />
                <flux:input wire:model="management_area" label="Gerencia" type="text" autocomplete="off" placeholder="Ej: HSEQ, Operaciones" />
                <flux:input wire:model="field" label="Campo" type="text" autocomplete="off" placeholder="Ej: PORE, SAN MARTIN" />
                <flux:input wire:model="location_zone" label="Ubicación / Zona" type="text" autocomplete="off" placeholder="Ej: ZONA 4, Bodega central" />
            </div>

            <div class="flex items-center gap-4">
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
