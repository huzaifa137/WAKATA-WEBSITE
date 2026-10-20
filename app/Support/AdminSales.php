<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Book;
use App\Models\Customer;
use App\Models\ExamSet;
use App\Models\FileDownload;
use App\Models\HolidayPackage;
use App\Models\Order;
use App\Models\Paper;

/**
 * The data the admin sales pages draw (orders, order detail, payments, deliveries, customers), in the shape the
 * prototype's admin scripts already use (see admin-data.js). Sent to the browser as WK.sales.
 */
class AdminSales
{
    public static function payload(?Admin $admin): array
    {
        $orders = Order::query()->with(['items', 'events'])->orderByDesc('created_at')->orderByDesc('id')->get();

        $customers = Customer::query()->with('addresses')->orderBy('id')->get();
        $keyed     = [];   // customer id (or guest key) => customer array
        $orderKey  = [];   // order id => key of its customer

        foreach ($customers as $c) {
            $keyed['C-' . (1000 + $c->id)] = self::customer($c);
        }

        // Guests (no account): grouped by email, or by phone when there is no email, newest order first so the newest name wins
        foreach ($orders as $o) {
            if ($o->customer_id && isset($keyed['C-' . (1000 + $o->customer_id)])) {
                $orderKey[$o->id] = 'C-' . (1000 + $o->customer_id);
                continue;
            }
            $who = strtolower($o->customer_email ?: $o->customer_phone);
            $key = 'G-' . strtoupper(substr(md5($who), 0, 6));
            $orderKey[$o->id] = $key;

            if (! isset($keyed[$key])) {
                $keyed[$key] = [
                    'id' => $key, 'dbId' => null, 'name' => $o->customer_name, 'type' => 'Guest', 'phone' => $o->customer_phone,
                    'email' => $o->customer_email, 'district' => '', 'contact' => '', 'note' => '', 'joined' => $o->created_at->getTimestampMs(),
                ];
            } else {
                $keyed[$key]['joined'] = min($keyed[$key]['joined'], $o->created_at->getTimestampMs());
            }
        }

        $downloads = FileDownload::query()->selectRaw('order_id, SUM(used) as used')->groupBy('order_id')->pluck('used', 'order_id');

        $list = $orders->map(fn (Order $o) => self::order($o, $orderKey[$o->id], (int) ($downloads[$o->id] ?? 0)))->values()->all();
        $who  = array_values($keyed);

        // Team members who do not work with orders (the content editor) see the numbers but not who the customers are
        $private = ! $admin?->canManageSales();
        if ($private) {
            $list = array_map([self::class, 'redactOrder'], $list);
            $who  = array_map([self::class, 'redactCustomer'], $who);
        }

        return [
            'orders'    => $list,
            'customers' => $who,
            'redacted'  => $private,
            'delivery'  => ShopSettings::delivery(),
            'payments'  => collect(config('wakata.payments_all'))->map(fn ($label, $key) => ['label' => $label, 'enabled' => ShopSettings::paymentFlags()[$key]])->all(),
            'types'     => Customer::TYPES,
            'can'       => ['sales' => (bool) $admin?->canManageSales(), 'payments' => (bool) $admin?->canManagePayments()],
            'routes'    => self::routes(),
            'items'     => self::sellable(),
        ];
    }

    /** "Green Hill School" becomes "Green H." (first word and the initial of the second): enough to recognise a repeat buyer in a chart, not enough to contact them. */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($parts) > 1 ? $parts[0] . ' ' . mb_strtoupper(mb_substr($parts[1], 0, 1)) . '.' : ($parts[0] ?? '');
    }

    private static function redactOrder(array $o): array
    {
        return array_merge($o, [
            'name' => self::shortName($o['name']), 'email' => '', 'phone' => '', 'address' => '', 'note' => '', 'adminNote' => '', 'courier' => '', 'txn' => '',
            'events' => array_map(fn ($e) => array_merge($e, ['note' => '']), $o['events']),
        ]);
    }

    private static function redactCustomer(array $c): array
    {
        return array_merge($c, ['name' => self::shortName($c['name']), 'email' => '', 'phone' => '', 'contact' => '', 'note' => '']);
    }

    /** One order in the prototype's shape. */
    public static function order(Order $o, ?string $custKey = null, int $downloads = 0): array
    {
        $o->loadMissing(['items', 'events']);

        return [
            'id'        => $o->number,
            'ts'        => $o->created_at->getTimestampMs(),
            'custId'    => $custKey ?? ($o->customer_id ? 'C-' . (1000 + $o->customer_id) : null),
            'name'      => $o->customer_name,
            'email'     => $o->customer_email,
            'phone'     => $o->customer_phone,
            'items'     => $o->items->map(fn ($i) => [
                'type' => $i->item_type, 'id' => $i->item_slug, 'fmt' => $i->format, 'qty' => $i->quantity, 'price' => $i->unit_price,
                'title' => $i->title, 'level' => $i->level, 'subject' => $i->subject,
            ])->all(),
            'delivery'  => $o->delivery_method,
            'method'    => $o->payment_method,
            'status'    => $o->status,
            'pay'       => $o->payment_status,
            'sub'       => $o->subtotal,
            'fee'       => $o->delivery_fee,
            'total'     => $o->total,
            'txn'       => (string) $o->transaction_ref,
            'note'      => (string) $o->note,
            'adminNote' => (string) $o->admin_note,
            'courier'   => (string) $o->courier_note,
            'address'   => (string) $o->delivery_address,
            'source'    => $o->source,
            'paidTs'    => $o->paid_at?->getTimestampMs(),
            'downloads' => $downloads ?: (int) FileDownload::where('order_id', $o->id)->sum('used'),
            'emailedTs' => $o->events->where('label', 'PDF files emailed')->last()?->created_at?->getTimestampMs(),
            'events'    => $o->events->map(fn ($e) => ['ts' => $e->created_at->getTimestampMs(), 'label' => $e->label, 'note' => (string) $e->note])->all(),
        ];
    }

    private static function customer(Customer $c): array
    {
        $default = $c->addresses->firstWhere('is_default', true) ?? $c->addresses->first();

        return [
            'id'       => 'C-' . (1000 + $c->id),
            'dbId'     => $c->id,
            'name'     => $c->name,
            'type'     => $c->type,
            'phone'    => (string) $c->phone,
            'email'    => $c->email,
            'district' => (string) ($c->district ?: ($default?->district ?? '')),
            'contact'  => (string) $c->contact_person,
            'note'     => (string) $c->admin_note,
            'joined'   => $c->created_at->getTimestampMs(),
        ];
    }

    public static function customerPayload(Customer $c): array
    {
        return self::customer($c->loadMissing('addresses'));
    }

    /** What a phone order can be made of: published products, labelled for the "Item or search" box. */
    private static function sellable(): array
    {
        return [
            'book'  => Book::published()->shopOrder()->get()->map(fn ($b) => ['id' => $b->slug, 'label' => $b->title])->all(),
            'paper' => Paper::published()->shopOrder()->get()->filter(fn (Paper $p) => $p->onSale())->map(fn ($p) => [
                'id' => $p->slug, 'label' => $p->title . ' ' . $p->year, 'print' => (bool) $p->print_on, 'pdf' => (bool) $p->pdf_on,
            ])->values()->all(),
            'set'   => ExamSet::published()->shopOrder()->get()->map(fn ($s) => ['id' => $s->slug, 'label' => $s->title])->all(),
            'pack'  => HolidayPackage::published()->shopOrder()->get()->map(fn ($k) => ['id' => $k->slug, 'label' => $k->name])->all(),
        ];
    }

    private static function routes(): array
    {
        return [
            'base'     => url('/admin'),
            'orders'   => route('admin.orders'),
            'order'    => route('admin.order'),
            'customers' => route('admin.customers'),
            'payments' => route('admin.payments'),
        ];
    }
}
