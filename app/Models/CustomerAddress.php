<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = ['customer_id', 'label', 'recipient', 'line', 'district', 'phone', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The shape the portal JS draws. */
    public function toPortal(): array
    {
        return [
            'id'       => $this->id,
            'label'    => $this->label,
            'name'     => $this->recipient,
            'line'     => $this->line,
            'district' => (string) $this->district,
            'phone'    => (string) $this->phone,
            'def'      => $this->is_default,
        ];
    }
}
