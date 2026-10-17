<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'item_type', 'item_id', 'item_slug', 'item_name', 'mode', 'quantity', 'delta', 'stock_after', 'reason', 'note',
        'admin_id', 'admin_name',
    ];

    protected function casts(): array
    {
        return ['item_id' => 'integer', 'quantity' => 'integer', 'delta' => 'integer', 'stock_after' => 'integer'];
    }
}
