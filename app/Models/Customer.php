<?php

namespace App\Models;

use App\Models\Concerns\HasInitials;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasInitials;

    public const TYPES = ['Teacher', 'School', 'Parent', 'Bookshop'];

    protected $fillable = ['name', 'email', 'phone', 'type', 'school', 'password', 'status', 'preview_data'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'preview_data'  => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** The small payload the front-end JS reads as WK.user (public header, checkout prefill, portal samples). */
    public function toFrontend(): array
    {
        return [
            'name'   => $this->name,
            'email'  => $this->email,
            'phone'  => $this->phone,
            'type'   => $this->type,
            'school' => $this->school,
            'fresh'  => ! $this->preview_data,
        ];
    }
}
