<?php

namespace App\Models;

use Database\Factories\KbArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KbArticle extends Model
{
    /** @use HasFactory<KbArticleFactory> */
    use HasFactory, SoftDeletes;

    // Campos controlados por el backend (author_id en Create page, counters
    // por triggers/feedback, published_at auto en publish) se excluyen del
    // fillable para que NO puedan inyectarse vía Livewire payload.
    protected $fillable = [
        'title', 'slug', 'body', 'kb_category_id', 'department_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'helpful_count' => 'integer',
            'not_helpful_count' => 'integer',
            'published_at' => 'datetime',
            'pending_review_at' => 'datetime',
        ];
    }

    /**
     * Artículos en Borrador que el autor marcó como listos para revisión
     * por un supervisor. Diferente de scopePublished: aquí mostramos a
     * los supervisores qué tienen pendiente aprobar.
     *
     * @param  Builder<self>  $query
     */
    public function scopePendingReview(Builder $query): void
    {
        $query->where('status', 'draft')->whereNotNull('pending_review_at');
    }

    /** @return BelongsTo<User, $this> */
    public function pendingReviewBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pending_review_by_id');
    }

    /** @return BelongsTo<KbCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<KbTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tag');
    }

    /** @return HasMany<KbArticleVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(KbArticleVersion::class);
    }

    /** @return HasMany<KbArticleFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(KbArticleFeedback::class);
    }

    /**
     * Artículos publicados — visibles en el chatbot (RagService) y en
     * el portal del solicitante cuando se agregue el resource allá.
     * Los Borrador y Archivado no se exponen nunca al usuario final.
     *
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * Snapshot the current content as a new version before editing.
     */
    public function createVersion(?int $editorId = null, ?string $summary = null): KbArticleVersion
    {
        $nextVersion = ($this->versions()->max('version_number') ?? 0) + 1;

        return KbArticleVersion::create([
            'kb_article_id' => $this->id,
            'editor_id' => $editorId,
            'version_number' => $nextVersion,
            'title' => $this->title,
            'body' => $this->body,
            'change_summary' => $summary,
        ]);
    }
}
