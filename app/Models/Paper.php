<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paper extends Model
{
    use FlushesCatalogue, Publishable;

    protected $fillable = [
        'slug', 'level_id', 'subject_id', 'paper', 'year', 'title', 'duration', 'print_price', 'pdf_price',
        'cover_image', 'pdf_path', 'status', 'featured_order', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer', 'print_price' => 'integer', 'pdf_price' => 'integer'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
