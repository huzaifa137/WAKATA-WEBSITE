<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedItem extends Model
{
    protected $fillable = ['customer_id', 'item_type', 'item_slug', 'format'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The shape the portal JS draws. `fmt` is null for everything except papers. */
    public function toPortal(): array
    {
        return [
            'id'   => $this->id,
            'type' => $this->item_type,
            'ref'  => $this->item_slug,
            'fmt'  => $this->format === '' ? null : $this->format,
        ];
    }
}
