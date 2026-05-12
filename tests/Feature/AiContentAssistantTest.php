<?php

use App\Services\AiContentAssistant;
use App\Services\LlmService;

it('parses a ticket template generation response with SUBJECT/DESCRIPTION markers', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(
            "SUBJECT: Reseteo de contraseña Outlook\n"
            ."DESCRIPTION:\n"
            ."## Contexto\nNo puedo iniciar sesión en Outlook.\n\n"
            ."## Detalles a proporcionar\n- Usuario afectado\n- Último login exitoso\n\n"
            ."## Resultado esperado\nContraseña reseteada y acceso restaurado.",
        );
    });

    $result = app(AiContentAssistant::class)->generateTicketTemplate('Reset contraseña Outlook');

    expect($result)->not->toBeNull();
    expect($result['subject'])->toBe('Reseteo de contraseña Outlook');
    expect($result['description'])->toContain('## Contexto');
    expect($result['description'])->toContain('## Resultado esperado');
});

it('returns null when the LLM is unavailable', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(null);
    });

    $result = app(AiContentAssistant::class)->generateTicketTemplate('algo');

    expect($result)->toBeNull();
});

it('returns null when the LLM output does not contain the expected markers', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn('Una respuesta cualquiera sin marcadores válidos.');
    });

    $result = app(AiContentAssistant::class)->generateTicketTemplate('algo');

    expect($result)->toBeNull();
});

it('parses a canned response with TITLE/BODY markers', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(
            "TITLE: Confirmar recepción y solicitar PC\n"
            ."BODY:\n"
            ."Hola [nombre],\n\nRecibimos tu solicitud. Para avanzar necesitamos **marca y modelo** del equipo afectado.\n\nQuedamos atentos.",
        );
    });

    $result = app(AiContentAssistant::class)->generateCannedResponse('Pedir marca y modelo del PC');

    expect($result)->not->toBeNull();
    expect($result['title'])->toBe('Confirmar recepción y solicitar PC');
    expect($result['body'])->toContain('Hola [nombre]');
    expect($result['body'])->toContain('marca y modelo');
});

it('refines a piece of text and returns the trimmed result', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn("   \nTexto refinado con menos palabras.\n   ");
    });

    $result = app(AiContentAssistant::class)->refine(
        'Texto largo lleno de detalles innecesarios.',
        'Hazlo más corto',
    );

    expect($result)->toBe('Texto refinado con menos palabras.');
});

it('caps the subject at 255 characters to fit the DB column', function () {
    $longSubject = str_repeat('a', 400);
    $this->mock(LlmService::class, function ($mock) use ($longSubject) {
        $mock->shouldReceive('chat')->andReturn("SUBJECT: {$longSubject}\nDESCRIPTION:\nBody.");
    });

    $result = app(AiContentAssistant::class)->generateTicketTemplate('largo');

    expect($result)->not->toBeNull();
    expect(mb_strlen($result['subject']))->toBe(255);
});
