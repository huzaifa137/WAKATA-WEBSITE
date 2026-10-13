<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayPackage extends Model
{
    use FlushesCatalogue, Publishable;

    protected $fillable = [
        'slug', 'level_id', 'name', 'audience', 'price', 'is_best', 'includes', 'timetable_path', 'status', 'featured_order', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_best' => 'boolean', 'includes' => 'array'];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
