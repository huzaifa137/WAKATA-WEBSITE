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

    /** Roles that may work orders, deliveries and customers (change status, create orders, add customers). Editors can look. */
    public function canManageSales(): bool
    {
        return in_array($this->role, ['super_admin', 'manager', 'support'], true);
    }

    /** Roles that may handle money: confirm or refund a payment, switch payment methods, change delivery fees. */
    public function canManagePayments(): bool
    {
        return in_array($this->role, ['super_admin', 'manager'], true);
    }

    /** Roles that answer messages, support questions and subscribers. */
    public function canManageInbox(): bool
    {
        return in_array($this->role, ['super_admin', 'manager', 'support'], true);
    }

    /** Roles that edit the words on the website, the testimonials and the questions and answers. */
    public function canManageContent(): bool
    {
        return in_array($this->role, ['super_admin', 'manager', 'editor'], true);
    }

    /** Roles that change shop settings (alerts, download limit, goal, analytics). */
    public function canManageSettings(): bool
    {
        return in_array($this->role, ['super_admin', 'manager'], true);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
    }
}
