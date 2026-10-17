<?php

namespace App\Support;

use App\Models\Book;
use App\Models\CatalogueOption;
use App\Models\ExamSet;
use App\Models\FileDownload;
use App\Models\HolidayPackage;
use App\Models\Level;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Paper;
use App\Models\StockAdjustment;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the admin catalogue pages (books, past papers, exam sets, packages, subjects and levels, media,
 * inventory) draw, read from the database and handed to the page scripts as WK.adm.
 *
 * It has the same shapes the shop catalogue uses (id = slug, level, subject, price, old, img ...) but includes
 * drafts and the admin-only fields: SKU, stock, alert level, formats on or off, files uploaded, real sales.
 */
class AdminCatalogue
{
    private const DISK = 'local';

    public static function payload(): array
    {
        $levels   = Level::orderBy('sort_order')->orderBy('id')->get();
        $subjects = Subject::orderBy('sort_order')->orderBy('id')->get();

        $levelCode = $levels->pluck('code', 'id');
        $bookCode  = $levels->pluck('book_code', 'id');
        $subject   = $subjects->pluck('name', 'id');

        $url = fn (?string $p) => Catalogue::imageUrl($p);

        $books = Book::orderBy('sort_order')->orderBy('id')->get()->map(fn (Book $b) => [
            'id'       => $b->slug,
            'title'    => $b->title,
            'subject'  => $subject[$b->subject_id] ?? '',
            'level'    => $bookCode[$b->level_id] ?? '',
            'price'    => $b->price,
            'old'      => $b->old_price,
            'badge'    => $b->badge ?? '',
            'img'      => $url($b->cover_image),
            'imgPath'  => $b->cover_image,
            'full'     => $url($b->back_image),
            'fullPath' => $b->back_image,
            'pages'    => $b->pages,
            'edition'  => $b->edition ?? '',
            'type'     => $b->format,
            'desc'     => $b->description,
            'meta'     => $b->meta_description ?? '',
            'sku'      => $b->sku,
            'stock'    => $b->stock,
            'low'      => $b->low_stock_at,
            'status'   => $b->status,
            'featured' => $b->featured_order !== null,
        ])->values()->all();

        $disk = Storage::disk(self::DISK);

        $papers = Paper::orderBy('sort_order')->orderBy('id')->get()->map(function (Paper $p) use ($levelCode, $subject, $url, $disk) {
            $o    = $p->options ?? [];
            $file = $p->pdf_path && $disk->exists($p->pdf_path);

            return [
                'id'         => $p->slug,
                'level'      => $levelCode[$p->level_id] ?? '',
                'subject'    => $subject[$p->subject_id] ?? '',
                'paper'      => $p->paper,
                'year'       => $p->year,
                'time'       => $p->duration,
                'print'      => $p->print_price,
                'pdf'        => $p->pdf_price,
                'img'        => $url($p->cover_image),
                'imgPath'    => $p->cover_image,
                'title'      => $p->title,
                'printOn'    => $p->print_on,
                'pdfOn'      => $p->pdf_on,
                'printStock' => $p->print_stock,
                'low'        => $p->low_stock_at,
                'hasFile'    => $file,
                'fileMb'     => $file ? max(0.1, round($disk->size($p->pdf_path) / 1048576, 1)) : null,
                'hasGuide'   => (bool) ($p->guide_path && $disk->exists($p->guide_path)),
                'bulk'       => $o['bulk'] ?? 30,
                'wm'         => $o['watermark'] ?? true,
                'auto'       => $o['auto_email'] ?? true,
                'status'     => $p->status,
            ];
        })->values()->all();

        $sets = ExamSet::orderBy('sort_order')->orderBy('id')->get()->map(function (ExamSet $s) use ($levelCode, $disk) {
            $o = $s->options ?? [];

            return [
                'id'       => $s->slug,
                'level'    => $levelCode[$s->level_id] ?? '',
                'title'    => $s->title,
                'year'     => ctype_digit((string) $s->year_label) ? (int) $s->year_label : $s->year_label,
                'price'    => $s->price,
                'worth'    => $s->worth_price,
                'papers'   => $s->papers_count,
                'subjects' => $s->contents ?? [],
                'preview'  => $s->previews ?? [],
                'blurb'    => $s->blurb,
                'sku'      => $s->sku,
                'stock'    => $s->stock,
                'low'      => $s->low_stock_at,
                'printOn'  => $o['print_on'] ?? true,
                'pdfOn'    => $o['pdf_on'] ?? false,
                'guides'   => $o['guides'] ?? true,
                'hasGuide' => (bool) ($s->guide_path && $disk->exists($s->guide_path)),
                'status'   => $s->status,
            ];
        })->values()->all();

        $packages = HolidayPackage::orderBy('sort_order')->orderBy('id')->get()->map(function (HolidayPackage $k) use ($levelCode, $disk) {
            $o = $k->options ?? [];

            return [
                'id'           => $k->slug,
                'level'        => $levelCode[$k->level_id] ?? '',
                'name'         => $k->name,
                'for'          => $k->audience,
                'price'        => $k->price,
                'best'         => $k->is_best,
                'includes'     => $k->includes ?? [],
                'sku'          => $k->sku,
                'stock'        => $k->stock,
                'low'          => $k->low_stock_at,
                'swap'         => $o['swap'] ?? true,
                'hasTimetable' => (bool) ($k->timetable_path && $disk->exists($k->timetable_path)),
                'status'       => $k->status,
            ];
        })->values()->all();

        $bookCount  = Book::selectRaw('subject_id, count(*) as n')->groupBy('subject_id')->pluck('n', 'subject_id');
        $paperCount = Paper::selectRaw('subject_id, count(*) as n')->groupBy('subject_id')->pluck('n', 'subject_id');

        return [
            'books'     => $books,
            'papers'    => $papers,
            'sets'      => $sets,
            'packages'  => $packages,
            'subjects'  => $subjects->map(fn (Subject $s) => [
                'id' => $s->id, 'name' => $s->name, 'c1' => $s->color_1 ?: '#043AA0', 'c2' => $s->color_2 ?: '#061B4D', 'c3' => $s->color_3 ?: '#FFD43B',
                'books' => (int) ($bookCount[$s->id] ?? 0), 'papers' => (int) ($paperCount[$s->id] ?? 0),
            ])->values()->all(),
            'levels'    => $levels->map(fn (Level $l) => [
                'id' => $l->id, 'code' => $l->code, 'label' => $l->label, 'short' => $l->short, 'book' => $l->book_code, 'bookLabel' => $l->book_label,
            ])->values()->all(),
            'types'     => self::types(),
            'badges'    => self::badges(),
            'paperDefaults' => self::paperDefaults(),
            'sold'      => self::sold(),
            'downloads' => self::downloads(),
            'movement'  => self::movement(),
            'media'     => Media::files(),
            'routes'    => self::routes(),
        ];
    }

    public static function types(): array
    {
        return CatalogueOption::read('book_types', ['Textbook']);
    }

    public static function badges(): array
    {
        return CatalogueOption::read('book_badges', []);
    }

    /** Default printed price, PDF price and exam time for a new paper of each level. */
    public static function paperDefaults(): array
    {
        $fallback = ['print' => 4000, 'pdf' => 2500, 'time' => '2 hours 15 minutes'];
        $saved    = CatalogueOption::read('paper_defaults', []);
        $out      = [];

        foreach (Level::orderBy('sort_order')->orderBy('id')->pluck('code') as $code) {
            $out[$code] = array_merge($fallback, $saved[$code] ?? []);
        }

        return $out;
    }

    /** Units sold per product: ['book' => ['slug' => 12], 'paper' => ...] from paid orders that were not cancelled or refunded. */
    public static function sold(): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->groupBy('order_items.item_type', 'order_items.item_slug')
            ->selectRaw('order_items.item_type as t, order_items.item_slug as s, sum(order_items.quantity) as q')
            ->get();

        $out = ['book' => [], 'paper' => [], 'set' => [], 'pack' => []];
        foreach ($rows as $r) {
            $out[$r->t][$r->s] = (int) $r->q;
        }

        return $out;
    }

    /** Downloads used per paper PDF (slug => count), from the customer library. */
    public static function downloads(): array
    {
        $items = OrderItem::query()->where('item_type', 'paper')->where('format', 'pdf')->get(['id', 'item_slug']);
        if ($items->isEmpty()) {
            return [];
        }

        $used = FileDownload::query()->whereIn('file_key', $items->map(fn ($i) => 'i' . $i->id))->pluck('used', 'file_key');
        $out  = [];
        foreach ($items as $i) {
            $out[$i->item_slug] = ($out[$i->item_slug] ?? 0) + (int) ($used['i' . $i->id] ?? 0);
        }

        return $out;
    }

    /** The last 10 weeks: printed stock received (positive adjustments, not stock that came back from a cancelled or refunded order) against units sold. */
    public static function movement(): array
    {
        $weeks = 10;
        $start = Carbon::now()->startOfWeek()->subWeeks($weeks - 1);

        $received = array_fill(0, $weeks, 0);
        $sold     = array_fill(0, $weeks, 0);

        $idx = fn ($ts) => (int) min($weeks - 1, max(0, floor($start->diffInDays(Carbon::parse($ts)->startOfDay(), false) / 7)));

        StockAdjustment::where('created_at', '>=', $start)->where('delta', '>', 0)->where('reason', '!=', 'return')->get(['delta', 'created_at'])
            ->each(function ($a) use (&$received, $idx) {
                $received[$idx($a->created_at)] += $a->delta;
            });

        OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->where('order_items.is_physical', true)
            ->where('orders.created_at', '>=', $start)
            ->get(['order_items.quantity as q', 'orders.created_at as at'])
            ->each(function ($r) use (&$sold, $idx) {
                $sold[$idx($r->at)] += (int) $r->q;
            });

        return [
            'labels'   => collect(range(0, $weeks - 1))->map(fn ($i) => 'Wk ' . ($i + 1))->all(),
            'received' => $received,
            'sold'     => $sold,
        ];
    }

    private static function routes(): array
    {
        return [
            'base'        => url('/admin'),
            'books'       => route('admin.books'),
            'bookForm'    => route('admin.book-form'),
            'papers'      => route('admin.past-papers'),
            'paperForm'   => route('admin.paper-form'),
            'sets'        => route('admin.exam-sets'),
            'setForm'     => route('admin.set-form'),
            'packages'    => route('admin.packages'),
            'packageForm' => route('admin.package-form'),
            'siteBook'    => route('site.book'),
            'siteBooks'   => route('site.books'),
            'sitePapers'  => route('site.papers'),
            'siteSets'    => route('site.sets'),
            'sitePackages' => route('site.packages'),
        ];
    }
}
