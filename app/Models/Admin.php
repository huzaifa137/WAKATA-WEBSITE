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

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'status', 'two_factor_enabled', 'invited_at', 'invited_by'];

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected function casts(): array
    {
        return [
            'password'           => 'hashed',
            'two_factor_enabled' => 'boolean',
            'otp_expires_at'     => 'datetime',
            'last_login_at'      => 'datetime',
            'invited_at'         => 'datetime',
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

    /** Roles that download reports and data exports, and schedule the emailed report. Looking at reports is open to everyone. */
    public function canExportReports(): bool
    {
        return in_array($this->role, ['super_admin', 'manager'], true);
    }

    /** Only a super admin invites, changes or removes team members. */
    public function canManageTeam(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * What each role can change, worked out from the can...() methods above so the table on Team and roles can never
     * disagree with what the console really allows. Looking is open to every signed-in team member.
     *
     * @return array<int, array{area:string, roles:array<string,bool>}>
     */
    public static function permissionMatrix(): array
    {
        $areas = [
            'Books, past papers, exam sets, packages, media and stock' => 'canManageCatalogue',
            'Orders, deliveries and customers'                         => 'canManageSales',
            'Confirming and refunding payments'                        => 'canManagePayments',
            'Messages, support questions and subscribers'              => 'canManageInbox',
            'Site content, testimonials and questions'                 => 'canManageContent',
            'Downloading reports and data exports'                     => 'canExportReports',
            'Settings'                                                 => 'canManageSettings',
            'Team and roles'                                           => 'canManageTeam',
        ];

        return collect($areas)->map(fn (string $method, string $area) => [
            'area'  => $area,
            'roles' => collect(array_keys(self::ROLES))->mapWithKeys(fn (string $role) => [$role => (new self(['role' => $role]))->{$method}()])->all(),
        ])->values()->all();
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst(str_replace('_', ' ', (string) $this->role));
    }
}
