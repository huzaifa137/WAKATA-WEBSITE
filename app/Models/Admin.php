<?php

namespace App\Models;

use App\Models\Concerns\HasInitials;
use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    use HasInitials;

    public const ROLES = [
        'super_admin' => 'Super admin',
        'manager'     => 'Manager',
        'editor'      => 'Editor',
        'support'     => 'Support',
    ];

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'status', 'two_factor_enabled'];

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected function casts(): array
    {
        return [
            'password'           => 'hashed',
            'two_factor_enabled' => 'boolean',
            'otp_expires_at'     => 'datetime',
            'last_login_at'      => 'datetime',
        ];
    }

    /** Roles that may change the catalogue (prices, stock, files). Support staff can look but not edit. */
    public function canManageCatalogue(): bool
    {
        return in_array($this->role, ['super_admin', 'manager', 'editor'], true);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
    }
}
