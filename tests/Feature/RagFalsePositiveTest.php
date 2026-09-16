<?php

use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;
use App\Services\RagService;

/**
 * Regresión del falso positivo reportado en producción (2026-09-16).
 *
 * Un usuario escribió "mi equipo no enciende" y el asistente respondió
 * con el artículo "Qué hacer si Outlook no recibe correos" — un tema
 * completamente distinto.
 *
 * Causa: el scoring del keywordSearch era `matches / total_stems`, sin
 * ponderar por rareza del término ni por dónde ocurría el match. Un
 * artículo largo que mencionara "equipo" y "no" en cualquier párrafo
 * alcanzaba similitud 1.0 y se devolvía literal como respuesta directa.
 */
beforeEach(function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(null);
        $mock->shouldReceive('embed')->andReturn(null);
    });

    // KB representativa: varios artículos que NO hablan de hardware,
    // pero cuyo cuerpo menciona palabras genéricas como "equipo".
    KbArticle::factory()->create([
        'title' => 'Qué hacer si Outlook no recibe correos',
        'slug' => 'outlook-no-recibe-correos',
        'body' => 'Si Outlook no recibe nuevos correos electrónicos, el problema '
            .'puede deberse a la conexión a Internet. Verifica la conexión antes de '
            .'reportar el incidente. Los compañeros de tu equipo indican que enviaron '
            .'un correo pero no aparece en Outlook. Revisa la carpeta de correo no '
            .'deseado. Si el equipo no sincroniza, presiona F9 para enviar y recibir.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    KbArticle::factory()->create([
        'title' => 'Cómo conectar Wi-Fi en la red corporativa',
        'slug' => 'conectar-wifi-corporativa',
        'body' => 'Para conectar tu equipo a la red corporativa selecciona la red y '
            .'escribe la contraseña. Si no aparece la red, reinicia el adaptador.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    KbArticle::factory()->create([
        'title' => 'Cómo instalar Microsoft Teams',
        'slug' => 'como-instalar-teams',
        'body' => 'Descarga Teams desde el sitio oficial e inicia sesión con tu '
            .'correo corporativo. Ejecuta el instalador en tu equipo.',
        'status' => 'published',
        'published_at' => now(),
    ]);
});

it('no devuelve un artículo irrelevante como respuesta directa (bug reportado)', function () {
    $rag = app(RagService::class);
    $results = $rag->search('mi equipo no enciende', topN: 3, threshold: 0.0);

    $top = $results->first();

    // Puede devolver algo como contexto débil para el LLM, pero NUNCA
    // debe cruzar el umbral de kb_medium (0.42) que lo mostraría literal.
    if ($top !== null) {
        expect($top['similarity'])->toBeLessThan(0.42);
    }
});

it('el ChatbotService no responde con un artículo de KB para la consulta reportada', function () {
    $user = User::factory()->create();
    $session = ChatSession::create([
        'user_id' => $user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    [$response, $meta] = app(ChatbotService::class)
        ->handleMessageWithMeta($session, 'mi equipo no enciende');

    // No debe salir por las rutas que devuelven el artículo literal.
    expect($meta['source_kind'])->not->toBeIn(['kb_high', 'kb_medium']);
    expect($response)->not->toContain('Outlook');
});

it('sigue respondiendo correctamente cuando la consulta SÍ coincide con el título', function () {
    $rag = app(RagService::class);
    $results = $rag->search('como instalo teams', topN: 1);

    expect($results)->not->toBeEmpty();
    expect($results->first()['article_title'])->toBe('Cómo instalar Microsoft Teams');
    expect($results->first()['similarity'])->toBeGreaterThanOrEqual(0.55);
});

it('un match solo en el cuerpo nunca supera a un match en el título', function () {
    $rag = app(RagService::class);
    $results = $rag->search('outlook correos', topN: 3);

    // El artículo cuyo TÍTULO contiene "Outlook" y "correos" debe ganar.
    expect($results->first()['article_title'])->toBe('Qué hacer si Outlook no recibe correos');
});

it('ignora palabras vacías como "no" que aparecen en todos los artículos', function () {
    $rag = app(RagService::class);

    // Consulta compuesta solo de stopwords → sin señal útil.
    $results = $rag->search('no es que no', topN: 3, threshold: 0.0);

    expect($results)->toBeEmpty();
});
