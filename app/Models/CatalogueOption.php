<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Small editable lists of the catalogue: book types, book badges, default paper prices (one row per key). */
class CatalogueOption extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function read(string $key, array $default = []): array
    {
        $row = static::query()->where('key', $key)->first();

        return $row && is_array($row->value) ? $row->value : $default;
    }

    public static function write(string $key, array $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
