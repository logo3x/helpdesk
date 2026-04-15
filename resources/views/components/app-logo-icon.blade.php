@php
    $classes = $attributes->get('class', 'object-contain');
@endphp
<img src="{{ asset('images/logo-confipetrol-dark.png') }}" alt="Confipetrol" class="block dark:hidden {{ $classes }}" {{ $attributes->except('class') }} />
<img src="{{ asset('images/logo-confipetrol.png') }}" alt="Confipetrol" class="hidden dark:block {{ $classes }}" {{ $attributes->except('class') }} />
