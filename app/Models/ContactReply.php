<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactReply extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['contact_message_id', 'admin_id', 'admin_name', 'kind', 'subject', 'body', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }
}
