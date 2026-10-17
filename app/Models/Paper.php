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
        'cover_image', 'pdf_path', 'guide_path', 'status', 'featured_order', 'sort_order',
        'print_on', 'pdf_on', 'print_stock', 'low_stock_at', 'options',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer', 'print_price' => 'integer', 'pdf_price' => 'integer',
            'print_on' => 'boolean', 'pdf_on' => 'boolean',
            'print_stock' => 'integer', 'low_stock_at' => 'integer',
            'options' => 'array',
        ];
    }

    /** "PLE Mathematics" for primary papers, "Mathematics Paper 1" for the rest (how the shop has always titled them). */
    public static function makeTitle(Level $level, string $subject, string $paper): string
    {
        return $level->code === 'ple' ? $level->short . ' ' . $subject : trim($subject . ' ' . $paper);
    }

    /** Sold at all? (a paper with both formats off is hidden from the shop even if published) */
    public function onSale(): bool
    {
        return $this->print_on || $this->pdf_on;
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
