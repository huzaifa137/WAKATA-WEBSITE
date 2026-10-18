<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Someone who signed up to "Get new papers first". The token is what the unsubscribe link in every email carries. */
class Subscriber extends Model
{
    public const SOURCES = ['Home page', 'Footer', 'Checkout', 'Contact page', 'Added by staff'];

    protected $fillable = ['email', 'source', 'status', 'token', 'subscribed_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscriber $s) {
            $s->token ??= Str::random(40);
            $s->subscribed_at ??= now();
            $s->email = Str::lower(trim($s->email));
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
