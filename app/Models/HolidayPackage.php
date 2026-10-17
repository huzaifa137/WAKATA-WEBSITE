<?php

namespace App\Models;

use App\Models\Concerns\AssignsSku;
use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayPackage extends Model
{
    use AssignsSku, FlushesCatalogue, Publishable;

    public const SKU_PREFIX = 'PK-';
    public const SKU_START  = 300;

    protected $fillable = [
        'slug', 'sku', 'level_id', 'name', 'audience', 'price', 'is_best', 'includes', 'timetable_path', 'status',
        'featured_order', 'sort_order', 'stock', 'low_stock_at', 'options',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer', 'is_best' => 'boolean', 'includes' => 'array',
            'stock' => 'integer', 'low_stock_at' => 'integer', 'options' => 'array',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
