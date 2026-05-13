<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['deploy.token' => 'test-token-1234567890abcdef']);
    File::ensureDirectoryExists(storage_path('logs'));
    @unlink(storage_path('logs/deploy.log'));
});

it('rejects requests without a token (401)', function () {
    $response = $this->postJson('/api/deploy');

    $response->assertStatus(401);
    expect($response->json('error'))->toBe('Unauthorized');
});

it('rejects requests with a wrong token (401)', function () {
    $response = $this->postJson('/api/deploy?token=wrong');

    $response->assertStatus(401);
});

it('rejects requests when DEPLOY_TOKEN is empty (fail-closed, even if caller passes ?token=)', function () {
    // Si el .env no tiene DEPLOY_TOKEN configurado, NADA debe poder
    // disparar el deploy — esta es una protección crítica para no
    // exponer el endpoint accidentalmente en un setup nuevo.
    config(['deploy.token' => '']);

    $response = $this->postJson('/api/deploy?token=anything');

    $response->assertStatus(401);
});

it('GET /api/deploy/log requires a valid token too', function () {
    $response = $this->getJson('/api/deploy/log');
    $response->assertStatus(401);

    $response = $this->getJson('/api/deploy/log?token=test-token-1234567890abcdef');
    $response->assertStatus(200);
});

it('GET /api/deploy/log returns idle when there is no log file yet', function () {
    $response = $this->getJson('/api/deploy/log?token=test-token-1234567890abcdef');

    $response->assertOk();
    expect($response->json('status'))->toBe('idle');
    expect($response->json('log'))->toBe('');
});

it('GET /api/deploy/log returns ok when log ends with [DEPLOY-DONE]', function () {
    File::put(storage_path('logs/deploy.log'),
        "[DEPLOY-START] 2026-05-13\n"
        ."git pull origin main\n"
        ."Already up to date.\n"
        .'[DEPLOY-DONE] 2026-05-13 17:45:00'
    );

    $response = $this->getJson('/api/deploy/log?token=test-token-1234567890abcdef');

    $response->assertOk();
    expect($response->json('status'))->toBe('ok');
    expect($response->json('log'))->toContain('[DEPLOY-DONE]');
});

it('GET /api/deploy/log returns failed when log ends with [DEPLOY-FAILED]', function () {
    File::put(storage_path('logs/deploy.log'),
        "[DEPLOY-START]\ncomposer install\n[DEPLOY-FAILED] code=1 at ..."
    );

    $response = $this->getJson('/api/deploy/log?token=test-token-1234567890abcdef');

    $response->assertOk();
    expect($response->json('status'))->toBe('failed');
});

it('rejects token mismatch even when lengths differ (no timing leak)', function () {
    $response = $this->postJson('/api/deploy?token=x');

    // El hash_equals devuelve false sin importar la longitud, así que
    // la respuesta es siempre 401, no error de longitud.
    $response->assertStatus(401);
});
