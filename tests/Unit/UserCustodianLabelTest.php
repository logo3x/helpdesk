<?php

use App\Models\User;

it('incluye la cédula cuando el usuario tiene identification cargada', function () {
    $user = new User([
        'name' => 'Juan Pérez',
        'email' => 'juan.perez@confipetrol.com',
        'identification' => '1098765432',
    ]);

    expect($user->custodianLabel())
        ->toBe('Juan Pérez · CC 1098765432 · juan.perez@confipetrol.com');
});

it('omite la cédula cuando identification es null', function () {
    $user = new User([
        'name' => 'Ana Gómez',
        'email' => 'ana@confipetrol.com',
        'identification' => null,
    ]);

    expect($user->custodianLabel())
        ->toBe('Ana Gómez · ana@confipetrol.com');
});

it('omite la cédula cuando identification es string vacío', function () {
    $user = new User([
        'name' => 'Luis Soto',
        'email' => 'luis@confipetrol.com',
        'identification' => '',
    ]);

    expect($user->custodianLabel())
        ->toBe('Luis Soto · luis@confipetrol.com');
});
