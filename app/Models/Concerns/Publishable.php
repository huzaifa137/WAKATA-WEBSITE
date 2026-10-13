<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Publishable
{
    public const PUBLISHED = 'published';
    public const DRAFT     = 'draft';

    /** Only what shoppers may see. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->getTable() . '.status', self::PUBLISHED);
    }

    /** The order the shop lists things in (admin can re-order later). */
    public function scopeShopOrder(Builder $query): Builder
    {
        return $query->orderBy($this->getTable() . '.sort_order')->orderBy($this->getTable() . '.id');
    }
}
