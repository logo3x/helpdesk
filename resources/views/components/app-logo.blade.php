@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand href="{{ url('/') }}" {{ $attributes }}>
        <x-slot name="logo">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol" class="block h-8 w-auto object-contain dark:hidden" />
            <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="hidden h-8 w-auto object-contain dark:block" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand href="{{ url('/') }}" {{ $attributes }}>
        <x-slot name="logo">
            <img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol" class="block h-8 w-auto object-contain dark:hidden" />
            <img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="hidden h-8 w-auto object-contain dark:block" />
        </x-slot>
    </flux:brand>
@endif
