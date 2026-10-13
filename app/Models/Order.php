<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'processing', 'ready', 'shipped', 'completed', 'cancelled', 'refunded'];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    /** Order numbers start here: the first order is WK-10001. */
    public const NUMBER_BASE = 10000;

    protected $fillable = [
        'number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone',
        'delivery_method', 'delivery_address', 'payment_method', 'payment_status', 'status', 'transaction_ref',
        'subtotal', 'delivery_fee', 'total', 'note', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'integer', 'delivery_fee' => 'integer', 'total' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getPaymentLabelAttribute(): string
    {
        return config('wakata.payments.' . $this->payment_method, $this->payment_method);
    }

    public function getDeliveryLabelAttribute(): string
    {
        return config('wakata.delivery.' . $this->delivery_method . '.label', $this->delivery_method);
    }

    public function getDeliveryEtaAttribute(): string
    {
        return config('wakata.delivery.' . $this->delivery_method . '.eta', '');
    }

    /** True when something has to be handed over or delivered (not only PDFs). */
    public function hasPhysicalItems(): bool
    {
        return $this->items->contains(fn (OrderItem $i) => $i->is_physical);
    }

    /** Revenue only counts paid orders that were not cancelled or refunded (same rule the prototype dashboards used). */
    public function scopeCounted($query)
    {
        return $query->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded']);
    }
}
