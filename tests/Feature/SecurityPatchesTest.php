<?php

use App\Livewire\Portal\Chatbot;
use App\Models\User;
use Livewire\Livewire;

// ── Chatbot: cap de longitud y strip_tags en ?q= ──
it('capa el initialQuery del chatbot a 500 chars', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $largeQuery = str_repeat('A', 10000);

    $component = Livewire::withQueryParams(['q' => $largeQuery])
        ->test(Chatbot::class);

    // Tras procesar, initialQuery queda vacío y el message tampoco
    // debe exceder 500 chars (que es lo que vamos a enviar al LLM).
    expect(strlen($component->get('message')))->toBeLessThanOrEqual(500);
    expect($component->get('initialQuery'))->toBe('');
});

it('strip_tags en initialQuery del chatbot', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $maliciousQuery = '<script>alert(1)</script>Mi pregunta';

    $component = Livewire::withQueryParams(['q' => $maliciousQuery])
        ->test(Chatbot::class);

    // El mensaje final no debe contener <script> ni </script>.
    $message = (string) $component->get('message');
    expect(str_contains($message, '<script>'))->toBeFalse();
    expect(str_contains($message, '</script>'))->toBeFalse();
});

it('initialQuery vacío despues de strip_tags no envia nada', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // ?q=<script></script> queda vacío tras strip_tags → no se envía
    $component = Livewire::withQueryParams(['q' => '<p></p>'])
        ->test(Chatbot::class);

    // La historia solo tiene el saludo inicial del asistente (1 mensaje).
    expect(count($component->get('history')))->toBe(1);
});
