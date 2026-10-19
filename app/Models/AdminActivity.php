<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of the activity log (module 10). Written by App\Support\Activity, never edited. */
class AdminActivity extends Model
{
    public $timestamps = false;

    protected $fillable = ['admin_id', 'admin_name', 'area', 'icon', 'verb', 'object', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
