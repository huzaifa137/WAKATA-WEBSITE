<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportThread extends Model
{
    public const TOPICS = ['Order or delivery', 'Bulk or school quotation', 'Mock examinations', 'Something else'];

    /** Thread numbers start here: the first one is T-1001. */
    public const NUMBER_BASE = 1000;

    protected $fillable = ['ref', 'customer_id', 'order_id', 'topic', 'subject', 'status', 'customer_read_at', 'last_message_at'];

    protected function casts(): array
    {
        return ['customer_read_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    /** True when WAKATA wrote something the customer has not opened yet. */
    public function hasUnreadReply(): bool
    {
        $last = $this->relationLoaded('messages') ? $this->messages->where('sender', 'staff')->last() : $this->messages()->where('sender', 'staff')->latest('id')->first();

        return $last && (! $this->customer_read_at || $this->customer_read_at->lt($last->created_at));
    }
}
