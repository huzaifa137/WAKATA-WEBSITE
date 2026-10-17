<?php

namespace App\Models;

use App\Models\Concerns\AssignsSku;
use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSet extends Model
{
    use AssignsSku, FlushesCatalogue, Publishable;

    public const SKU_PREFIX = 'SET-';
    public const SKU_START  = 200;

    protected $fillable = [
        'slug', 'sku', 'level_id', 'title', 'year_label', 'price', 'worth_price', 'papers_count', 'contents', 'previews',
        'blurb', 'guide_path', 'status', 'sort_order', 'stock', 'low_stock_at', 'options',
    ];

    protected function casts(): array
    {
        return [
            'price'        => 'integer',
            'worth_price'  => 'integer',
            'papers_count' => 'integer',
            'stock'        => 'integer',
            'low_stock_at' => 'integer',
            'contents'     => 'array',
            'previews'     => 'array',
            'options'      => 'array',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
