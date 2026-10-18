<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    public const PAGES = ['contact' => 'Contact page', 'holiday' => 'Holiday packages page'];

    protected $fillable = ['page', 'question', 'answer', 'icon', 'visible', 'sort_order'];

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeFor(Builder $q, string $page): Builder
    {
        return $q->where('page', $page)->where('visible', true)->orderBy('sort_order')->orderBy('id');
    }

    /** The drawing for this question's icon (see config/faq_icons.php). */
    public function svg(): string
    {
        $inner = config('faq_icons.' . $this->icon) ?? config('faq_icons.help');

        return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
    }
}
