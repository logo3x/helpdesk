<?php

use App\Models\KbArticle;
use App\Models\User;
use App\Notifications\KbArticleRejectedNotification;
use Illuminate\Support\Facades\Notification;

it('la notificación de rechazo contiene el motivo + autor + revisor', function () {
    $author = User::factory()->create(['name' => 'Pedro Autor']);
    $reviewer = User::factory()->create(['name' => 'Lucía Supervisor']);
    $article = KbArticle::factory()->create([
        'title' => 'Reset de VPN',
        'author_id' => $author->id,
        'pending_review_at' => now(),
    ]);

    Notification::fake();

    $author->notify(new KbArticleRejectedNotification($article, $reviewer, 'Falta el paso de validación post-reset.'));

    Notification::assertSentTo($author, KbArticleRejectedNotification::class, function ($notif) use ($article, $reviewer) {
        return $notif->article->id === $article->id
            && $notif->rejectedBy->id === $reviewer->id
            && str_contains($notif->reason, 'validación post-reset');
    });
});

it('database payload incluye motivo en el body', function () {
    $author = User::factory()->create();
    $reviewer = User::factory()->create(['name' => 'Supervisor TI']);
    $article = KbArticle::factory()->create(['title' => 'VPN', 'author_id' => $author->id]);

    $payload = (new KbArticleRejectedNotification($article, $reviewer, 'Necesita capturas de pantalla'))
        ->toDatabase($author);

    expect($payload)->toHaveKey('body')
        ->and($payload['body'])->toContain('Necesita capturas de pantalla')
        ->and($payload['body'])->toContain('Supervisor TI');
});

it('correo de rechazo incluye el motivo en el cuerpo', function () {
    $author = User::factory()->create(['name' => 'Ana']);
    $reviewer = User::factory()->create(['name' => 'Lucía']);
    $article = KbArticle::factory()->create(['title' => 'VPN', 'slug' => 'vpn-test', 'author_id' => $author->id]);

    $mail = (new KbArticleRejectedNotification($article, $reviewer, 'Detalla los códigos de error'))
        ->toMail($author);

    $rendered = implode(' ', $mail->introLines);
    expect($rendered)->toContain('Detalla los códigos de error')
        ->and($rendered)->toContain('Lucía');
});
