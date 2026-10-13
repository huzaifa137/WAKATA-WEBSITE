<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'item_type', 'item_id', 'item_slug', 'format', 'title', 'level', 'subject', 'year', 'paper_no',
        'unit_price', 'quantity', 'line_total', 'is_physical',
    ];

    protected function casts(): array
    {
        return [
            'item_id'     => 'integer',
            'year'        => 'integer',
            'unit_price'  => 'integer',
            'quantity'    => 'integer',
            'line_total'  => 'integer',
            'is_physical' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
