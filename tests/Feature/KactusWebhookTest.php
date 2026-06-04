<?php

use App\Jobs\ProcessKactusWebhookJob;
use App\Models\User;
use App\Services\KactusService;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('kactus.enabled', true);
    config()->set('kactus.webhook_secret', 'test-secret-1234567890');
    Role::firstOrCreate(['name' => 'usuario_final', 'guard_name' => 'web']);
});

function signedRequest(array $payload, string $secret = 'test-secret-1234567890'): array
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    return ['body' => $body, 'signature' => $signature];
}

it('rechaza webhook sin firma con 401', function () {
    $response = $this->postJson('/api/kactus/webhook', ['employee_id' => 'K-1']);

    $response->assertStatus(401);
});

it('rechaza webhook con firma inválida con 401', function () {
    $response = $this->postJson(
        '/api/kactus/webhook',
        ['employee_id' => 'K-1'],
        ['X-Kactus-Signature' => 'firma-mala']
    );

    $response->assertStatus(401);
});

it('encola job cuando firma es válida', function () {
    Queue::fake();

    $payload = ['employee_id' => 'K-99', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active'];
    $signed = signedRequest($payload);

    $response = $this->call(
        method: 'POST',
        uri: '/api/kactus/webhook',
        server: ['HTTP_X-Kactus-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        content: $signed['body'],
    );

    $response->assertStatus(202);
    Queue::assertPushed(ProcessKactusWebhookJob::class, function ($job) {
        return $job->payload['employee_id'] === 'K-99';
    });
});

it('responde 503 si la integración está deshabilitada', function () {
    config()->set('kactus.enabled', false);

    $signed = signedRequest(['employee_id' => 'K-1']);

    $response = $this->call(
        method: 'POST',
        uri: '/api/kactus/webhook',
        server: ['HTTP_X-Kactus-Signature' => $signed['signature'], 'CONTENT_TYPE' => 'application/json'],
        content: $signed['body'],
    );

    $response->assertStatus(503);
});

it('responde 503 si no hay webhook_secret configurado', function () {
    config()->set('kactus.webhook_secret', '');

    $response = $this->postJson('/api/kactus/webhook', ['employee_id' => 'K-1']);

    $response->assertStatus(503);
});

it('procesa el job y crea/actualiza el user', function () {
    $payload = [
        'employee_id' => 'K-JOB-1',
        'document_number' => '77777777',
        'first_name' => 'Job',
        'last_name' => 'Processed',
        'email' => 'jobtest@confipetrol.com',
        'status' => 'active',
    ];

    (new ProcessKactusWebhookJob($payload))->handle(app(KactusService::class));

    expect(User::where('kactus_employee_id', 'K-JOB-1')->exists())->toBeTrue();
});
