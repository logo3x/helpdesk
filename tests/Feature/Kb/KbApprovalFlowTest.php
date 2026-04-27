<?php

use App\Filament\Soporte\Resources\KbArticles\Pages\EditKbArticle;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\User;
use App\Notifications\KbArticlePublishedNotification;
use App\Notifications\KbArticleReviewRequestedNotification;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleSeeder::class]);
    $this->seed(ShieldPermissionSeeder::class);

    // Necesario para que Livewire pueda renderizar la página de
    // EditKbArticle (resuelve route bindings del panel).
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    $this->deptTi = Department::factory()->create(['name' => 'TI', 'slug' => 'ti']);

    $this->supervisor = User::factory()->create(['department_id' => $this->deptTi->id]);
    $this->supervisor->assignRole('supervisor_soporte');

    $this->agente = User::factory()->create(['department_id' => $this->deptTi->id]);
    $this->agente->assignRole('agente_soporte');
});

it('agente solicita publicación → notifica a supervisores del mismo depto', function () {
    Notification::fake();

    $article = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);
    $article->forceFill(['author_id' => $this->agente->id])->save();

    Livewire::actingAs($this->agente)
        ->test(EditKbArticle::class, ['record' => $article->id])
        ->callAction('requestReview');

    $fresh = $article->fresh();
    expect($fresh->pending_review_at)->not->toBeNull();
    expect($fresh->pending_review_by_id)->toBe($this->agente->id);

    Notification::assertSentTo($this->supervisor, KbArticleReviewRequestedNotification::class);
});

it('agente puede cancelar su propia solicitud', function () {
    $article = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);
    $article->forceFill([
        'author_id' => $this->agente->id,
        'pending_review_at' => now(),
        'pending_review_by_id' => $this->agente->id,
    ])->save();

    Livewire::actingAs($this->agente)
        ->test(EditKbArticle::class, ['record' => $article->id])
        ->callAction('cancelReview');

    $fresh = $article->fresh();
    expect($fresh->pending_review_at)->toBeNull();
    expect($fresh->pending_review_by_id)->toBeNull();
});

it('supervisor aprueba y publica → notifica al autor', function () {
    Notification::fake();

    $article = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);
    $article->forceFill([
        'author_id' => $this->agente->id,
        'pending_review_at' => now(),
        'pending_review_by_id' => $this->agente->id,
    ])->save();

    Livewire::actingAs($this->supervisor)
        ->test(EditKbArticle::class, ['record' => $article->id])
        ->callAction('approveAndPublish');

    $fresh = $article->fresh();
    expect($fresh->status)->toBe('published');
    expect($fresh->published_at)->not->toBeNull();
    expect($fresh->pending_review_at)->toBeNull();
    expect($fresh->pending_review_by_id)->toBeNull();

    Notification::assertSentTo($this->agente, KbArticlePublishedNotification::class);
});

it('supervisor no ve la acción approveAndPublish si el artículo no está pendiente', function () {
    $article = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);

    Livewire::actingAs($this->supervisor)
        ->test(EditKbArticle::class, ['record' => $article->id])
        ->assertActionHidden('approveAndPublish');
});

it('agente no ve la acción approveAndPublish (solo supervisor)', function () {
    $article = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);
    $article->forceFill([
        'author_id' => $this->agente->id,
        'pending_review_at' => now(),
        'pending_review_by_id' => $this->agente->id,
    ])->save();

    Livewire::actingAs($this->agente)
        ->test(EditKbArticle::class, ['record' => $article->id])
        ->assertActionHidden('approveAndPublish');
});

it('scopePendingReview filtra solo borradores marcados para revisión', function () {
    $pending = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);
    $pending->forceFill(['pending_review_at' => now()])->save();

    $justDraft = KbArticle::factory()->create([
        'status' => 'draft',
        'department_id' => $this->deptTi->id,
    ]);

    KbArticle::factory()->published()->create([
        'department_id' => $this->deptTi->id,
    ]);

    $ids = KbArticle::query()->pendingReview()->pluck('id')->all();
    expect($ids)->toContain($pending->id);
    expect($ids)->not->toContain($justDraft->id);
});
