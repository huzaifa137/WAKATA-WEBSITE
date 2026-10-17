<?php

namespace App\Models;

use App\Models\Concerns\AssignsSku;
use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use App\Support\Catalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Book extends Model
{
    use AssignsSku, FlushesCatalogue, Publishable;

    public const SKU_PREFIX = 'BK-';
    public const SKU_START  = 100;

    protected $fillable = [
        'slug', 'sku', 'title', 'subject_id', 'level_id', 'price', 'old_price', 'badge', 'cover_image', 'back_image',
        'pages', 'edition', 'format', 'description', 'meta_description', 'status', 'featured_order', 'sort_order',
        'stock', 'low_stock_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer', 'old_price' => 'integer', 'pages' => 'integer',
            'stock' => 'integer', 'low_stock_at' => 'integer',
        ];
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
