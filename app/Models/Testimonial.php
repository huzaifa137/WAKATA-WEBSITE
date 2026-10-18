<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = ['stars', 'quote', 'initials', 'role', 'place', 'visible', 'permission', 'sort_order'];

    protected function casts(): array
    {
        return ['stars' => 'integer', 'visible' => 'boolean', 'permission' => 'boolean', 'sort_order' => 'integer'];
    }

    /** What the home page shows, in the order staff set. */
    public function scopeShown(Builder $q): Builder
    {
        return $q->where('visible', true)->orderBy('sort_order')->orderBy('id');
    }
}
