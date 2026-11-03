<?php

use App\Models\User;
use App\Notifications\LlmModelSwitchedNotification;
use App\Services\LlmService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Vigilante de la IA: cuando el modelo se cae, el sistema busca y
 * activa otro solo, y avisa a los administradores.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    Cache::forget(LlmService::ACTIVE_MODEL_CACHE_KEY);
    Cache::forget(LlmService::DOWN_NOTICE_CACHE_KEY);

    config([
        'services.llm.provider' => 'nvidia',
        'services.llm.api_key' => 'test-key',
        'services.llm.model' => 'preferido/caido',
        'services.llm.fallback_model' => 'respaldo/caido',
        'services.llm.model_candidates' => '',
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

/**
 * @param  array<int, string>  $working  modelos que responden
 */
function fakeModels(array $working, array $catalog = []): void
{
    Http::fake(function (Request $request) use ($working, $catalog) {
        if (str_ends_with($request->url(), '/models')) {
            return Http::response(['data' => array_map(fn ($id) => ['id' => $id], $catalog)]);
        }

        if (in_array($request['model'], $working, true)) {
            return Http::response(['choices' => [['message' => ['content' => 'Ve a portal de contraseñas y sigue los pasos.']]]]);
        }

        throw new ConnectionException('cURL error 28: Operation timed out');
    });
}

it('si el modelo activo responde no cambia nada', function () {
    fakeModels(['preferido/caido']);

    expect(app(LlmService::class)->autoHeal()['status'])->toBe('ok');
    Notification::assertNothingSent();
});

it('si se cae busca en el catálogo, activa el que funciona y avisa a los admins', function () {
    fakeModels(['catalogo/funciona'], ['nvidia/embed-algo', 'catalogo/caido', 'catalogo/funciona']);

    $result = app(LlmService::class)->autoHeal();

    expect($result['status'])->toBe('switched')
        ->and($result['model'])->toBe('catalogo/funciona')
        ->and(LlmService::activeModel())->toBe('catalogo/funciona')
        ->and($result['tried'])->not->toContain('nvidia/embed-algo');

    Notification::assertSentTo($this->admin, LlmModelSwitchedNotification::class,
        fn ($n) => $n->previous === 'preferido/caido' && $n->current === 'catalogo/funciona');
});

it('vuelve al modelo preferido cuando se recupera', function () {
    Cache::forever(LlmService::ACTIVE_MODEL_CACHE_KEY, 'temporal/funciona');
    fakeModels(['preferido/caido', 'temporal/funciona']);

    $result = app(LlmService::class)->autoHeal();

    expect($result['status'])->toBe('restored')
        ->and(LlmService::activeModel())->toBe('preferido/caido')
        ->and(Cache::has(LlmService::ACTIVE_MODEL_CACHE_KEY))->toBeFalse();
});

it('si ningún modelo responde avisa una sola vez', function () {
    fakeModels([], ['otro/caido']);
    $llm = app(LlmService::class);

    expect($llm->autoHeal()['status'])->toBe('down');
    $llm->autoHeal();

    Notification::assertSentToTimes($this->admin, LlmModelSwitchedNotification::class, 1);
});

it('en una conversación, si el respaldo responde queda como activo', function () {
    config(['services.llm.fallback_model' => 'respaldo/funciona']);
    fakeModels(['respaldo/funciona']);

    $llm = app(LlmService::class);
    $llm->chat([['role' => 'user', 'content' => 'hola']]);

    expect(LlmService::activeModel())->toBe('respaldo/funciona');

    // El siguiente usuario va directo al modelo que funciona, sin
    // esperar el timeout del preferido.
    $before = count(Http::recorded());
    app(LlmService::class)->chat([['role' => 'user', 'content' => 'hola']]);

    $models = collect(Http::recorded())->slice($before)->map(fn ($pair) => $pair[0]['model'])->values()->all();

    expect($models)->toBe(['respaldo/funciona']);
});
