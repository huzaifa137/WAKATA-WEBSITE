<?php

namespace App\Models\Concerns;

/**
 * Gives a new record the next free SKU (BK-100, BK-101 ... ) when none is typed in.
 * The model sets SKU_PREFIX and SKU_START.
 */
trait AssignsSku
{
    protected static function bootAssignsSku(): void
    {
        static::creating(function ($model) {
            if (blank($model->sku)) {
                $model->sku = static::nextSku();
            }
        });
    }

    public static function nextSku(): string
    {
        $n = static::SKU_START + static::query()->count();

        while (static::query()->where('sku', static::SKU_PREFIX . $n)->exists()) {
            $n++;
        }

        return static::SKU_PREFIX . $n;
    }
}
