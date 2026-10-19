<?php

namespace App\Support;

use App\Models\Book;
use App\Models\CatalogueOption;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Paper;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The "Ready-made reports" of the Reports page (module 10): five tables built straight from the orders, as CSV or PDF,
 * and the short summary that the scheduled email carries. Money is in whole UGX. Only paid orders that were not cancelled
 * or refunded count as revenue (Order::scopeCounted), the same rule the dashboard uses.
 */
class ReportExport
{
    public const KEYS = ['sales', 'orders', 'products', 'customers', 'payments', 'stock'];

    public const RANGES = [7, 30, 90, 365];

    private const TITLES = [
        'sales'     => 'Sales summary',
        'orders'    => 'Orders and fulfilment',
        'products'  => 'Product performance',
        'customers' => 'Customers',
        'payments'  => 'Payments',
        'stock'     => 'Stock position',
    ];

    public static function title(string $key): string
    {
        return self::TITLES[$key];
    }

    public static function range(mixed $days): int
    {
        $days = (int) $days;

        return in_array($days, self::RANGES, true) ? $days : 90;
    }

    public static function from(int $days): Carbon
    {
        return now()->subDays($days)->startOfDay();
    }

    /**
     * @return array{head:array<int,string>, rows:array<int,array<int,string|int>>, numeric:array<int,bool>}
     */
    public static function build(string $key, int $days): array
    {
        $from = self::from($days);

        return match ($key) {
            'sales'     => self::sales($from),
            'orders'    => self::orders($from),
            'products'  => self::products($from),
            'customers' => self::customers($from),
            'payments'  => self::payments($from),
            'stock'     => self::stock(),
        };
    }

    private static function sales(Carbon $from): array
    {
        $byDay = [];
        foreach (Order::counted()->where('created_at', '>=', $from)->orderBy('created_at')->get(['created_at', 'subtotal', 'delivery_fee', 'total']) as $o) {
            $d = $o->created_at->format('Y-m-d');
            $byDay[$d] ??= [$d, 0, 0, 0, 0];
            $byDay[$d][1]++;
            $byDay[$d][2] += $o->subtotal;
            $byDay[$d][3] += $o->delivery_fee;
            $byDay[$d][4] += $o->total;
        }

        return ['head' => ['Date', 'Orders', 'Products (UGX)', 'Delivery fees (UGX)', 'Revenue (UGX)'], 'rows' => array_values($byDay), 'numeric' => [1 => true, 2 => true, 3 => true, 4 => true]];
    }

    private static function orders(Carbon $from): array
    {
        $rows = Order::where('created_at', '>=', $from)->orderByDesc('created_at')->get()->map(fn (Order $o) => [
            $o->number, $o->created_at->format('Y-m-d H:i'), $o->customer_name, $o->status, $o->payment_status,
            config('wakata.payments_all')[$o->payment_method] ?? $o->payment_method,
            config('wakata.delivery_all')[$o->delivery_method]['label'] ?? $o->delivery_method, $o->total,
        ])->all();

        return ['head' => ['Order', 'Placed', 'Customer', 'Status', 'Payment', 'Method', 'Delivery', 'Total (UGX)'], 'rows' => $rows, 'numeric' => [7 => true]];
    }

    private static function products(Carbon $from): array
    {
        $rows = self::productRows($from)->map(fn ($r) => [
            ['book' => 'Book', 'paper' => 'Past paper', 'set' => 'Exam set', 'pack' => 'Holiday package'][$r->item_type] ?? $r->item_type,
            $r->title, $r->format === 'pdf' ? 'PDF' : ($r->format === 'print' ? 'Printed' : ''), (int) $r->units, (int) $r->revenue,
        ])->all();

        return ['head' => ['Type', 'Product', 'Format', 'Units sold', 'Revenue (UGX)'], 'rows' => $rows, 'numeric' => [3 => true, 4 => true]];
    }

    /** Best sellers of the period, by revenue. */
    public static function productRows(Carbon $from, ?int $limit = null)
    {
        $q = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->where('orders.created_at', '>=', $from)
            ->groupBy('order_items.item_type', 'order_items.item_slug', 'order_items.format', 'order_items.title')
            ->selectRaw('order_items.item_type, order_items.item_slug, order_items.format, order_items.title, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->orderByDesc('revenue');

        return ($limit ? $q->limit($limit) : $q)->get();
    }

    private static function customers(Carbon $from): array
    {
        $rows = Order::counted()->where('created_at', '>=', $from)->get(['customer_name', 'customer_email', 'customer_phone', 'total'])
            ->groupBy(fn (Order $o) => strtolower($o->customer_email))
            ->map(fn ($g) => [$g->last()->customer_name, $g->first()->customer_email, $g->last()->customer_phone, $g->count(), $g->sum('total')])
            ->sortByDesc(fn ($r) => $r[4])->values()->all();

        return ['head' => ['Customer', 'Email', 'Phone', 'Orders', 'Spent (UGX)'], 'rows' => $rows, 'numeric' => [3 => true, 4 => true]];
    }

    private static function payments(Carbon $from): array
    {
        $rows = Order::where('created_at', '>=', $from)->orderByDesc('created_at')->get()->map(fn (Order $o) => [
            $o->number, ($o->paid_at ?? $o->created_at)->format('Y-m-d H:i'), config('wakata.payments_all')[$o->payment_method] ?? $o->payment_method,
            $o->payment_status, (string) $o->transaction_ref, $o->total,
        ])->all();

        return ['head' => ['Order', 'Date', 'Method', 'Status', 'Reference', 'Amount (UGX)'], 'rows' => $rows, 'numeric' => [5 => true]];
    }

    /** What is on the shelf now (the period does not matter): printed stock against each alert level, lowest first. */
    private static function stock(): array
    {
        $rows = [];
        $add  = function (string $type, string $name, string $sku, int $stock, int $alert, bool $onSale) use (&$rows) {
            $rows[] = [$type, $name, $sku, $stock, $alert, ! $onSale ? 'Not sold printed' : ($stock <= 0 ? 'Out of stock' : ($stock <= $alert ? 'Low' : 'OK'))];
        };

        foreach (Book::orderBy('title')->get() as $b) {
            $add('Book', $b->title, (string) $b->sku, $b->stock, $b->low_stock_at, true);
        }
        foreach (Paper::orderBy('title')->orderBy('year')->get() as $p) {
            $add('Past paper', $p->title . ' ' . $p->year, '', $p->print_stock, $p->low_stock_at, (bool) $p->print_on);
        }
        foreach (ExamSet::orderBy('title')->get() as $e) {
            $add('Exam set', $e->title, (string) $e->sku, $e->stock, $e->low_stock_at, true);
        }
        foreach (HolidayPackage::orderBy('name')->get() as $k) {
            $add('Holiday package', $k->name, (string) $k->sku, $k->stock, $k->low_stock_at, true);
        }

        usort($rows, fn ($a, $b) => [$a[5] === 'Not sold printed', $a[3] - $a[4]] <=> [$b[5] === 'Not sold printed', $b[3] - $b[4]]);

        return ['head' => ['Type', 'Product', 'SKU', 'On hand', 'Alert at', 'Status'], 'rows' => $rows, 'numeric' => [3 => true, 4 => true]];
    }

    /** A cell safe to open in a spreadsheet: text that starts like a formula is neutralised. */
    public static function cell(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && strpos("=+-@\t\r", $text[0]) !== false && ! is_numeric($text) ? "'" . $text : $text;
    }

    // ------------------------------------------------------------- the emailed report

    /** @return array{frequency:string, recipients:string, last_sent:?string} frequency: off | weekly | monthly */
    public static function schedule(): array
    {
        $saved = CatalogueOption::read('report_schedule');

        return [
            'frequency'  => in_array($saved['frequency'] ?? 'off', ['off', 'weekly', 'monthly'], true) ? ($saved['frequency'] ?? 'off') : 'off',
            'recipients' => (string) ($saved['recipients'] ?? ''),
            'last_sent'  => $saved['last_sent'] ?? null,
        ];
    }

    public static function saveSchedule(string $frequency, string $recipients): void
    {
        CatalogueOption::write('report_schedule', ['frequency' => $frequency, 'recipients' => $recipients, 'last_sent' => self::schedule()['last_sent']]);
    }

    public static function markSent(): void
    {
        $s = self::schedule();
        CatalogueOption::write('report_schedule', ['frequency' => $s['frequency'], 'recipients' => $s['recipients'], 'last_sent' => now()->toDateString()]);
    }

    /**
     * The numbers of the emailed report.
     *
     * @return array{revenue:int, orders:int, average:int, open:int, top:array<int, array{0:string,1:string,2:string,3:string}>}
     */
    public static function summary(int $days): array
    {
        $from    = self::from($days);
        $counted = Order::counted()->where('created_at', '>=', $from);
        $revenue = (int) (clone $counted)->sum('total');
        $orders  = (int) (clone $counted)->count();

        $top = self::productRows($from, 5)->map(fn ($r) => [
            $r->title . ($r->format === 'pdf' ? ' (PDF)' : ''), (string) (int) $r->units,
            'UGX ' . number_format($r->units ? (int) round($r->revenue / $r->units) : 0), 'UGX ' . number_format((int) $r->revenue),
        ])->all();

        return [
            'revenue' => $revenue,
            'orders'  => $orders,
            'average' => $orders ? (int) round($revenue / $orders) : 0,
            'open'    => Order::whereIn('status', ['pending', 'confirmed', 'processing'])->count(),
            'top'     => $top,
        ];
    }
}
