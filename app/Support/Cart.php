<?php

namespace App\Support;

use App\Models\Book;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Paper;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Arr;

/**
 * The shopping cart, kept in the visitor's session (module 5).
 *
 * The session only remembers WHAT was added: {k, type, id, fmt, qty}. Titles and prices are always read from the
 * database when the cart is shown or an order is placed, so the browser can never choose a price, and an item that
 * has been unpublished simply drops out of the cart.
 *
 *   type  book | paper | set | pack        id  the catalogue slug (same ids the page scripts use)
 *   fmt   papers only: print | pdf         k   "type:id:fmt", one line per product and format
 */
class Cart
{
    public const KEY       = 'wk_cart';
    public const MAX_LINES = 40;
    public const MAX_QTY   = 99;
    public const ADMIN_MAX_QTY = 5000;   // staff typing in a school's bulk order
    public const TYPES     = ['book', 'paper', 'set', 'pack'];

    public function __construct(private Session $session)
    {
    }

    public static function current(): self
    {
        return new self(request()->session());
    }

    /** Number of units in the cart (cheap: only reads the session). */
    public static function countFor(Session $session): int
    {
        return (int) collect($session->get(self::KEY, []))->sum('qty');
    }

    public static function lineKey(string $type, string $id, ?string $fmt): string
    {
        return implode(':', [$type, $id, $fmt ?? '']);
    }

    /** Clean one entry from the browser; null when it can not be a real product reference. */
    public static function normalise(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $type = (string) ($row['type'] ?? '');
        $id   = (string) ($row['id'] ?? '');
        if (! in_array($type, self::TYPES, true) || ! preg_match('/^[A-Za-z0-9._-]{1,120}$/', $id)) {
            return null;
        }

        $fmt = null;
        if ($type === 'paper') {
            $fmt = ($row['fmt'] ?? 'print') === 'pdf' ? 'pdf' : 'print';
        }

        $qty = (int) ($row['qty'] ?? 1);

        return [
            'k'    => self::lineKey($type, $id, $fmt),
            'type' => $type,
            'id'   => $id,
            'fmt'  => $fmt,
            'qty'  => max(1, min(self::MAX_QTY, $qty)),
        ];
    }

    /** @return array<int, array{k:string,type:string,id:string,fmt:?string,qty:int}> */
    public function raw(): array
    {
        return array_values((array) $this->session->get(self::KEY, []));
    }

    public function count(): int
    {
        return self::countFor($this->session);
    }

    /**
     * Add products. Only items that exist and are published are kept.
     *
     * @param  array<int, mixed>  $rows
     * @return array{added:int, skipped:int}
     */
    public function add(array $rows): array
    {
        $wanted = [];
        foreach ($rows as $row) {
            if ($n = self::normalise($row)) {
                // The same product twice in one request adds up
                $wanted[$n['k']] = isset($wanted[$n['k']]) ? array_merge($n, ['qty' => min(self::MAX_QTY, $wanted[$n['k']]['qty'] + $n['qty'])]) : $n;
            }
        }

        $found = self::resolve(array_values($wanted));
        $cart  = collect($this->raw())->keyBy('k');
        $added = $out = $limited = 0;

        foreach ($wanted as $k => $row) {
            if (! isset($found[$k])) {
                continue;
            }
            if (! $cart->has($k) && $cart->count() >= self::MAX_LINES) {
                continue;
            }
            $have = $cart->get($k);
            $qty  = min(self::MAX_QTY, ($have['qty'] ?? 0) + $row['qty']);

            // With "stop selling out-of-stock items" on, a cart never holds more than the shelf has
            $cap = self::cap($found[$k]);
            if ($cap !== null && $qty > $cap) {
                if ($cap < 1) {
                    $out++;

                    continue;
                }
                $qty = $cap;
                $limited++;
            }

            $cart->put($k, array_merge($row, ['qty' => $qty]));
            $added++;
        }

        $this->store($cart->values()->all());

        return ['added' => $added, 'skipped' => count($wanted) - $added, 'out' => $out, 'limited' => $limited];
    }

    public function setQty(string $key, int $qty): void
    {
        $cart = collect($this->raw())->map(function ($line) use ($key, $qty) {
            if ($line['k'] === $key) {
                $line['qty'] = max(1, min(self::MAX_QTY, $qty));
            }

            return $line;
        });

        $this->store($cart->all());
    }

    public function remove(string $key): void
    {
        $this->store(collect($this->raw())->reject(fn ($l) => $l['k'] === $key)->values()->all());
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * The cart with live prices, ready for the browser and for checkout.
     * Lines whose product is gone or unpublished are dropped (and counted in `removed`).
     *
     * @return array{lines: array<int, array>, count:int, subtotal:int, physical:bool, removed:int}
     */
    public function priced(): array
    {
        $raw      = $this->raw();
        $products = self::resolve($raw);

        $lines   = [];
        $soldOut = $reduced = 0;
        foreach ($raw as $row) {
            if (! isset($products[$row['k']])) {
                continue;                                   // gone or unpublished: counted in `removed` below
            }

            $p   = $products[$row['k']];
            $qty = $row['qty'];

            // Stock fell since the item was added: keep what is left, drop the line when nothing is
            $cap = self::cap($p);
            if ($cap !== null && $qty > $cap) {
                if ($cap < 1) {
                    $soldOut++;

                    continue;
                }
                $qty = $cap;
                $reduced++;
            }

            $lines[] = $p + ['qty' => $qty, 'line' => $p['price'] * $qty];
        }

        $removed  = count($raw) - count($lines) - $soldOut;
        $adjusted = $soldOut + $reduced;
        if ($removed > 0 || $adjusted > 0) {
            $this->store(array_map(fn ($l) => Arr::only($l, ['k', 'type', 'id', 'fmt', 'qty']), $lines));
        }

        return [
            'lines'    => $lines,
            'count'    => (int) array_sum(array_column($lines, 'qty')),
            'subtotal' => (int) array_sum(array_column($lines, 'line')),
            'physical' => collect($lines)->contains(fn ($l) => $l['phys']),
            'removed'  => $removed,
            'adjusted' => $adjusted,
        ];
    }

    /**
     * The most of a line a customer may hold, or null when there is no limit (stock rule off, or a PDF).
     * Only the shop's online cart uses it: staff taking a phone order (priceLines) may still go over.
     */
    private static function cap(array $product): ?int
    {
        if (! SiteSettings::get('stock_block') || $product['stock'] === null) {
            return null;
        }

        return max(0, (int) $product['stock']);
    }

    private function store(array $lines): void
    {
        if ($lines) {
            $this->session->put(self::KEY, array_values($lines));
        } else {
            $this->session->forget(self::KEY);
        }
    }

    /**
     * Price some lines the way the shop does (published products only, formats switched off refused). Used by the admin
     * "Create order" screen so a phone order is priced exactly like an online one.
     *
     * @param  array<int, array{type:string,id:string,fmt:?string,qty:int}>  $rows
     * @return array<int, array>  priced lines (same shape as priced()['lines'])
     */
    public static function priceLines(array $rows): array
    {
        $norm = [];
        foreach ($rows as $row) {
            if ($n = self::normalise($row)) {
                $n['qty'] = max(1, min(self::ADMIN_MAX_QTY, (int) ($row['qty'] ?? 1)));
                $norm[]   = $n;
            }
        }
        $products = self::resolve($norm);

        $lines = [];
        foreach ($norm as $n) {
            if (isset($products[$n['k']])) {
                $p       = $products[$n['k']];
                $lines[] = $p + ['qty' => $n['qty'], 'line' => $p['price'] * $n['qty']];
            }
        }

        return $lines;
    }

    /**
     * Look the products up in the database (one query per product type).
     *
     * @param  array<int, array>  $rows  normalised {k,type,id,fmt}
     * @return array<string, array>       line key => product facts (price is the unit price in UGX)
     */
    private static function resolve(array $rows): array
    {
        $slugs = [];
        foreach ($rows as $r) {
            $slugs[$r['type']][] = $r['id'];
        }

        $models = [
            'book'  => fn ($ids) => Book::published()->with(['level', 'subject'])->whereIn('slug', $ids)->get(),
            'paper' => fn ($ids) => Paper::published()->with(['level', 'subject'])->whereIn('slug', $ids)->get(),
            'set'   => fn ($ids) => ExamSet::published()->with('level')->whereIn('slug', $ids)->get(),
            'pack'  => fn ($ids) => HolidayPackage::published()->with('level')->whereIn('slug', $ids)->get(),
        ];

        $by = [];
        foreach ($slugs as $type => $ids) {
            $by[$type] = $models[$type](array_values(array_unique($ids)))->keyBy('slug');
        }

        $out = [];
        foreach ($rows as $r) {
            $m = $by[$r['type']][$r['id']] ?? null;
            if (! $m) {
                continue;
            }
            // A paper format the admin switched off cannot be bought
            if ($r['type'] === 'paper' && ! ($r['fmt'] === 'pdf' ? $m->pdf_on : $m->print_on)) {
                continue;
            }

            // Printed units on the shelf (null = no limit: a PDF download)
            $stock = $r['type'] === 'paper' ? ($r['fmt'] === 'pdf' ? null : (int) $m->print_stock) : (int) $m->stock;
            $base  = ['k' => $r['k'], 'type' => $r['type'], 'id' => $r['id'], 'fmt' => $r['fmt'], 'item_id' => $m->id, 'stock' => $stock];

            $out[$r['k']] = $base + match ($r['type']) {
                'book' => [
                    'title'   => $m->title,
                    'sub'     => $m->level->book_label . ' ' . $m->subject->name,
                    'price'   => $m->price,
                    'phys'    => true,
                    'level'   => $m->level->code,
                    'subject' => $m->subject->name,
                    'year'    => null,
                    'paperNo' => null,
                ],
                'paper' => [
                    'title'   => $m->title . ' ' . $m->year,
                    'sub'     => $m->level->short . ' past paper, ' . ($r['fmt'] === 'pdf' ? 'PDF download' : 'printed copy'),
                    'price'   => $r['fmt'] === 'pdf' ? $m->pdf_price : $m->print_price,
                    'phys'    => $r['fmt'] !== 'pdf',
                    'level'   => $m->level->code,
                    'subject' => $m->subject->name,
                    'year'    => $m->year,
                    'paperNo' => trim(preg_replace('/^paper\s*/i', '', (string) $m->paper)),
                ],
                'set' => [
                    'title'   => $m->title,
                    'sub'     => $m->papers_count . ' papers, printed set',
                    'price'   => $m->price,
                    'phys'    => true,
                    'level'   => $m->level->code,
                    'subject' => null,
                    'year'    => null,
                    'paperNo' => null,
                ],
                'pack' => [
                    'title'   => $m->name,
                    'sub'     => 'Holiday package for ' . $m->audience,
                    'price'   => $m->price,
                    'phys'    => true,
                    'level'   => $m->level->code,
                    'subject' => null,
                    'year'    => null,
                    'paperNo' => null,
                ],
            };
        }

        return $out;
    }
}
