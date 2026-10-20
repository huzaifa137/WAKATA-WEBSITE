<?php

namespace App\Support;

use App\Models\CatalogueOption;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * Settings that are not words on a page (module 9): who gets the team alerts, how many times a PDF can be downloaded, the
 * monthly sales goal and the analytics ID. One JSON list (catalogue_options, key "site_settings"), cached.
 * The public email is the Contact email of Site content, so there is one place to change it (see SiteContent).
 */
class SiteSettings
{
    private const CACHE_KEY = 'wakata.site_settings.v1';

    public const DEFAULTS = [
        'goal'             => 40000000,   // monthly sales goal, UGX (the dashboard uses it)
        'downloads'        => 5,          // downloads allowed per paid PDF; 0 = unlimited
        'alert_recipients' => '',         // comma-separated emails; empty = the addresses in .env
        'alert_orders'     => true,       // email the team about every new order
        'alert_messages'   => true,       // ... and about every new message or support question
        'stock_block'      => false,      // true = printed items cannot be added to a cart or ordered beyond the stock on hand
        'ga4'              => '',         // Google Analytics measurement ID, e.g. G-ABC123XYZ
    ];

    private static function saved(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => CatalogueOption::read('site_settings'));
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function get(string $key): mixed
    {
        return array_key_exists($key, self::saved()) ? self::saved()[$key] : self::DEFAULTS[$key];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return collect(self::DEFAULTS)->map(fn ($d, $k) => self::get($k))->all();
    }

    public static function save(array $values): void
    {
        $saved = self::saved();
        foreach ($values as $k => $v) {
            if (array_key_exists($k, self::DEFAULTS)) {
                $saved[$k] = $v;
            }
        }
        CatalogueOption::write('site_settings', $saved);
        self::refresh();
    }

    public static function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::apply();
    }

    /** Runs on every request: the download limit the library enforces. */
    public static function apply(): void
    {
        $n = (int) self::get('downloads');
        config(['wakata.download_limit' => $n > 0 ? $n : 1000000]);
    }

    /**
     * Who to email about something that needs the team. $kind: orders | messages.
     * An empty list when that alert is switched off or no address is known.
     *
     * @return array<int, string>
     */
    public static function alertRecipients(string $kind): array
    {
        if (! (bool) self::get($kind === 'orders' ? 'alert_orders' : 'alert_messages')) {
            return [];
        }

        $list = trim((string) self::get('alert_recipients'));
        if ($list === '') {
            $list = (string) ($kind === 'orders' ? config('wakata.order_notify') : (config('wakata.support_notify') ?: config('wakata.order_notify')));
        }

        // Team members who switched the alert on under My profile get it too
        $mine = \App\Models\Admin::where('status', 'active')->get()->filter(fn ($a) => $a->wantsAlert($kind))->pluck('email')->all();

        return self::emails($list . ',' . implode(',', $mine));
    }

    /** @return array<int, string> valid addresses only */
    public static function emails(string $list): array
    {
        return collect(preg_split('/[\s,;]+/', $list, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($e) => Validator::make(['e' => $e], ['e' => 'email'])->passes())->unique()->values()->all();
    }
}
