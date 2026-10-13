<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use App\Support\Catalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Book extends Model
{
    use FlushesCatalogue, Publishable;

    protected $fillable = [
        'slug', 'title', 'subject_id', 'level_id', 'price', 'old_price', 'badge', 'cover_image', 'back_image',
        'pages', 'edition', 'format', 'description', 'status', 'featured_order', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'integer', 'old_price' => 'integer', 'pages' => 'integer'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return Catalogue::imageUrl($this->cover_image);
    }
}
