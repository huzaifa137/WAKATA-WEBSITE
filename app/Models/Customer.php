<?php

namespace App\Models;

use App\Models\Concerns\HasInitials;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasInitials;

    public const TYPES = ['Teacher', 'School', 'Parent', 'Bookshop'];

    /** Defaults for the Notifications, Payment numbers and Delivery settings (stored in the `preferences` JSON). */
    public const DEFAULT_PREFERENCES = [
        'notify_orders'    => true,    // order updates
        'notify_files'     => true,    // PDF ready
        'notify_replies'   => true,    // replies from WAKATA
        'notify_news'      => false,   // new papers and mock series
        'notify_holidays'  => false,   // holiday package reminders
        'mtn_number'       => '',
        'airtel_number'    => '',
        'prefill_payment'  => true,
        'show_stamp'       => true,    // leave a school stamp area on printed invoices
        'delivery_method'  => 'kampala',
        'delivery_notes'   => '',
    ];

    protected $fillable = [
        'name', 'email', 'phone', 'type', 'school', 'invoice_name', 'org_address', 'accounts_email', 'password', 'status',
        'two_step_enabled', 'preferences',
    ];

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected function casts(): array
    {
        return [
            'password'         => 'hashed',
            'two_step_enabled' => 'boolean',
            'preferences'      => 'array',
            'otp_expires_at'   => 'datetime',
            'last_login_at'    => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(SavedItem::class);
    }

    public function supportThreads(): HasMany
    {
        return $this->hasMany(SupportThread::class);
    }

    /** One setting, falling back to the default. */
    public function pref(string $key): mixed
    {
        return ($this->preferences ?? [])[$key] ?? (self::DEFAULT_PREFERENCES[$key] ?? null);
    }

    /** All settings with defaults filled in. */
    public function allPreferences(): array
    {
        return array_replace(self::DEFAULT_PREFERENCES, $this->preferences ?? []);
    }

    /** Name that goes on invoices: the organisation name when set, otherwise the school, otherwise the person. */
    public function billingName(): string
    {
        return $this->invoice_name ?: ($this->school ?: $this->name);
    }

    /** The small payload the front-end JS reads as WK.user (public header, checkout prefill, portal). */
    public function toFrontend(): array
    {
        $default = $this->loadMissing('addresses')->addresses->firstWhere('is_default', true);

        return [
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'type'    => $this->type,
            'school'  => $this->school,
            // Checkout pre-fill: the default delivery address, when there is one
            'address' => $default ? trim($default->line . ($default->district ? ', ' . $default->district : '')) : null,
            'delivery' => $this->pref('delivery_method'),
        ];
    }
}
