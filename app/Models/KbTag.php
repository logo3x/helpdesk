<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KbTag extends Model
{
    protected $fillable = ['name', 'slug'];

    /** @return BelongsToMany<KbArticle, $this> */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tag');
    }
}
