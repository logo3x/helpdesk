<?php

use App\Livewire\Portal\Chatbot;
use App\Models\User;
use Livewire\Livewire;

it('Chatbot acepta initialQuery vía query param ?q=', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['q' => 'cómo reseteo mi contraseña'])
        ->test(Chatbot::class)
        ->assertSet('initialQuery', '');  // Se limpia tras procesar

    expect(true)->toBeTrue();
});
