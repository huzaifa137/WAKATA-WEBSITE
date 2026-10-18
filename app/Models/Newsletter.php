<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = ['subject', 'body', 'audience', 'sent_count', 'failed_count', 'admin_id', 'admin_name', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
