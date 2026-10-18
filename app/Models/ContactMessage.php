<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A message sent from the Contact page. Staff answer it from Messages (by email) and can send a quotation. */
class ContactMessage extends Model
{
    public const STATUSES = ['new', 'read', 'replied', 'quoted', 'archived'];

    public const TOPICS = ['Order or delivery', 'Bulk or school quotation', 'Mock examinations', 'Something else'];

    public const REF_BASE = 1000;

    protected $fillable = ['ref', 'customer_id', 'name', 'email', 'phone', 'topic', 'body', 'status', 'read_at', 'replied_at', 'ip_address'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'replied_at' => 'datetime'];
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
