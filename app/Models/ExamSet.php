<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogue;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSet extends Model
{
    use FlushesCatalogue, Publishable;

    protected $fillable = [
        'slug', 'level_id', 'title', 'year_label', 'price', 'worth_price', 'papers_count', 'contents', 'previews',
        'blurb', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price'        => 'integer',
            'worth_price'  => 'integer',
            'papers_count' => 'integer',
            'contents'     => 'array',
            'previews'     => 'array',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
