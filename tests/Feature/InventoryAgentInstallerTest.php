<?php

it('serves the install script with the token pre-filled when provided', function () {
    $response = $this->get('/agent/install?token=1|abcdef1234567890');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = $response->getContent();

    // El token debe quedar embebido en una variable PowerShell.
    expect($body)->toContain("\$Token = '1|abcdef1234567890'");

    // Las mejoras de v2 deben estar presentes.
    expect($body)->toContain('DPAPI');
    expect($body)->toContain('LocalMachine');
    expect($body)->toContain('token.enc');
    expect($body)->toContain('AtStartup');
});

it('prompts for the token interactively when none is supplied', function () {
    $response = $this->get('/agent/install');

    $response->assertOk();
    $body = $response->getContent();

    expect($body)->toContain('Read-Host');
});

it('rejects malformed tokens to avoid arbitrary script injection', function () {
    $response = $this->get('/agent/install?token=BAD<script>');

    $response->assertStatus(400);
    expect($response->getContent())->toContain('Token inválido');
});

it('serves the uninstall script without requiring auth', function () {
    $response = $this->get('/agent/uninstall');

    $response->assertOk();
    $body = $response->getContent();

    expect($body)->toContain('Unregister-ScheduledTask');
    expect($body)->toContain('HelpdeskConfipetrol');
});

it('serves the .ps1 agent script with text/plain so IIS does not block it', function () {
    $response = $this->get('/agent/script');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = $response->getContent();

    // El header de PowerShell debe estar.
    expect($body)->toContain('Helpdesk Confipetrol');
    expect($body)->toContain('inventory-agent');
});

it('install script points to /agent/script (not the legacy static path)', function () {
    $response = $this->get('/agent/install?token=1|abcdef1234567890');
    $body = $response->getContent();

    // Regresión: si el installer apuntara a /downloads/inventory-agent.ps1
    // de nuevo, IIS lo bloquearía. Tiene que ir por la ruta Laravel.
    expect($body)->toContain('/agent/script');
    expect($body)->not->toContain('/downloads/inventory-agent.ps1');
});
