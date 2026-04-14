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

    protected $fillable = [
        'title', 'slug', 'body', 'kb_category_id', 'author_id',
        'status', 'visibility', 'views_count', 'helpful_count',
        'not_helpful_count', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'helpful_count' => 'integer',
            'not_helpful_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KbCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'kb_category_id');
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

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /** @param Builder<self> $query */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('visibility', 'public')->published();
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
