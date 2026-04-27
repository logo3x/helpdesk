<?php

use App\Livewire\Portal\KbIndex;
use App\Livewire\Portal\KbShow;
use App\Models\KbArticle;
use App\Models\KbArticleFeedback;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

describe('KbIndex (lista)', function () {
    it('lista solo artículos publicados', function () {
        $published = KbArticle::factory()->published()->create(['title' => 'Cómo cambiar contraseña']);
        KbArticle::factory()->create(['title' => 'Borrador secreto']);
        KbArticle::factory()->archived()->create(['title' => 'Política antigua']);

        Livewire::actingAs($this->user)
            ->test(KbIndex::class)
            ->assertSee($published->title)
            ->assertDontSee('Borrador secreto')
            ->assertDontSee('Política antigua');
    });

    it('filtra por término de búsqueda en título y cuerpo', function () {
        KbArticle::factory()->published()->create([
            'title' => 'Reset de contraseña Outlook',
            'body' => 'Pasos para restablecer.',
        ]);
        KbArticle::factory()->published()->create([
            'title' => 'Solicitar vacaciones',
            'body' => 'Llenar formato F-RH-02.',
        ]);

        Livewire::actingAs($this->user)
            ->test(KbIndex::class)
            ->set('search', 'Outlook')
            ->assertSee('Reset de contraseña Outlook')
            ->assertDontSee('Solicitar vacaciones');
    });

    it('limpia los filtros al llamar clearFilters', function () {
        Livewire::actingAs($this->user)
            ->test(KbIndex::class)
            ->set('search', 'algo')
            ->set('department', '1')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('department', '')
            ->assertSet('category', '');
    });
});

describe('KbShow (detalle)', function () {
    it('retorna 404 cuando el artículo está en borrador', function () {
        $draft = KbArticle::factory()->create(['title' => 'Borrador']);

        Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $draft->slug])
            ->assertStatus(404);
    });

    it('retorna 404 cuando el artículo está archivado', function () {
        $archived = KbArticle::factory()->archived()->create();

        Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $archived->slug])
            ->assertStatus(404);
    });

    it('incrementa views_count solo la primera vez en la sesión', function () {
        $article = KbArticle::factory()->published()->create(['views_count' => 0]);

        Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $article->slug]);

        expect($article->fresh()->views_count)->toBe(1);

        // Mismo usuario en la misma "sesión" abre otra vez → no debería incrementar.
        Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $article->slug]);

        expect($article->fresh()->views_count)->toBe(1);
    });

    it('registra feedback útil y actualiza el contador', function () {
        $article = KbArticle::factory()->published()->create();

        Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $article->slug])
            ->call('vote', true)
            ->assertSet('userVote', true);

        expect($article->fresh()->helpful_count)->toBe(1);
        expect($article->fresh()->not_helpful_count)->toBe(0);
        expect(KbArticleFeedback::where('user_id', $this->user->id)
            ->where('kb_article_id', $article->id)
            ->value('is_helpful'))->toBeTrue();
    });

    it('cambia el voto y reajusta los contadores sin duplicar', function () {
        $article = KbArticle::factory()->published()->create();

        $component = Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $article->slug])
            ->call('vote', true);

        expect($article->fresh()->helpful_count)->toBe(1);

        $component->call('vote', false)
            ->assertSet('userVote', false);

        $fresh = $article->fresh();
        expect($fresh->helpful_count)->toBe(0);
        expect($fresh->not_helpful_count)->toBe(1);
        // Solo debe existir una fila de feedback (no se duplica al cambiar voto).
        expect(KbArticleFeedback::where('user_id', $this->user->id)
            ->where('kb_article_id', $article->id)
            ->count())->toBe(1);
    });

    it('no hace nada si el usuario vota lo mismo dos veces', function () {
        $article = KbArticle::factory()->published()->create();

        $component = Livewire::actingAs($this->user)
            ->test(KbShow::class, ['slug' => $article->slug])
            ->call('vote', true);

        expect($article->fresh()->helpful_count)->toBe(1);

        $component->call('vote', true);

        expect($article->fresh()->helpful_count)->toBe(1);
        expect(KbArticleFeedback::where('user_id', $this->user->id)
            ->where('kb_article_id', $article->id)
            ->count())->toBe(1);
    });
});
