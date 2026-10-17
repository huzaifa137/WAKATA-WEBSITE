<?php

namespace App\Support;

use App\Models\CatalogueOption;
use Illuminate\Support\Facades\Cache;

/**
 * Checkout options the staff can change (module 8): delivery names, times and fees, and which payment methods are on.
 *
 * The shop code keeps reading config('wakata.delivery') and config('wakata.payments'). apply() runs on every request and
 * rewrites those two lists from the saved settings, so a switched-off option disappears from the cart page and is refused
 * by checkout, and a new fee is the fee the server charges. config('wakata.delivery_all') and config('wakata.payments_all')
 * keep every option for the admin screens and for the labels of old orders.
 */
class ShopSettings
{
    private const CACHE_KEY = 'wakata.shop_settings.v1';

    /** The delivery options staff can edit. "email" (PDF delivery) is fixed: it is not a choice, it is what happens to PDF-only orders. */
    public const EDITABLE_DELIVERY = ['pickup', 'kampala', 'upcountry'];

    public static function apply(): void
    {
        try {
            $saved = Cache::rememberForever(self::CACHE_KEY, fn () => [
                'delivery' => CatalogueOption::read('delivery_options'),
                'payments' => CatalogueOption::read('payment_methods'),
            ]);
        } catch (\Throwable $e) {
            return; // before the first migration there is no table yet: keep the shipped options
        }

        $all = [];
        foreach (config('wakata.delivery_base') as $key => $base) {
            $o = $saved['delivery'][$key] ?? [];

            $all[$key] = [
                'label'   => trim((string) ($o['label'] ?? '')) ?: $base['label'],
                'eta'     => trim((string) ($o['eta'] ?? '')) ?: $base['eta'],
                'fee'     => isset($o['fee']) ? max(0, (int) $o['fee']) : $base['fee'],
                'enabled' => $key === 'email' ? true : (bool) ($o['enabled'] ?? true),
            ];
        }

        $payments = config('wakata.payments_base');
        $on       = array_filter($payments, fn ($label, $key) => (bool) ($saved['payments'][$key] ?? true), ARRAY_FILTER_USE_BOTH);

        config([
            'wakata.delivery_all' => $all,
            'wakata.delivery'     => array_filter($all, fn ($d) => $d['enabled']),
            'wakata.payments_all' => $payments,
            'wakata.payments'     => $on,
        ]);
    }

    /** @return array<string, array{label:string, eta:string, fee:int, enabled:bool}> every delivery option, switched off ones included */
    public static function delivery(): array
    {
        return config('wakata.delivery_all');
    }

    /** @return array<string, bool> payment method => is it on for customers */
    public static function paymentFlags(): array
    {
        return collect(config('wakata.payments_all'))->map(fn ($label, $key) => array_key_exists($key, config('wakata.payments')))->all();
    }

    /** @param array<string, array{label:string, eta:string, fee:int, enabled:bool}> $rows keyed by pickup, kampala, upcountry */
    public static function saveDelivery(array $rows): void
    {
        $clean = [];
        foreach (self::EDITABLE_DELIVERY as $key) {
            $r = $rows[$key];
            $clean[$key] = ['label' => trim($r['label']), 'eta' => trim($r['eta']), 'fee' => (int) $r['fee'], 'enabled' => (bool) $r['enabled']];
        }

        CatalogueOption::write('delivery_options', $clean);
        self::refresh();
    }

    /** @param array<string, bool> $flags */
    public static function savePayments(array $flags): void
    {
        $clean = [];
        foreach (array_keys(config('wakata.payments_base')) as $key) {
            $clean[$key] = (bool) ($flags[$key] ?? false);
        }

        CatalogueOption::write('payment_methods', $clean);
        self::refresh();
    }

    private static function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::apply();
    }
}
