<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\ExamSet;
use App\Models\FileDownload;
use App\Models\HolidayPackage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Paper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Downloads and library": which files a customer may download, and how many downloads each one has left.
 *
 * Files come from PAID orders (not cancelled or refunded):
 *   - every PDF past paper the customer bought                          key  i{order item id}
 *   - marking guides that come with an exam set, once it has shipped    key  i{id}-guide
 *   - the revision timetable of a holiday package, once completed       key  i{id}-timetable
 * The real files live on the private disk (papers.pdf_path, exam_sets.guide_path, holiday_packages.timetable_path).
 * Each file can be downloaded FileDownload::limit() times per order (5 by default).
 */
class Library
{
    private const DISK = 'local';

    /**
     * Every file of the customer's paid orders, newest first, in the shape the portal JS draws.
     *
     * @return array<int, array>
     */
    public static function forCustomer(Customer $customer): array
    {
        $orders = Order::query()->where('customer_id', $customer->id)->counted()->with('items')->latest('id')->get();

        return self::describe($orders)->values()->all();
    }

    /** The files of one order, keyed by file key. */
    public static function forOrder(Order $order): array
    {
        return self::describe(collect([$order->loadMissing('items')]))->keyBy('key')->all();
    }

    public static function find(Order $order, string $key): ?array
    {
        return self::forOrder($order)[$key] ?? null;
    }

    /** Path of the real file on the private disk, or null when none has been uploaded (or it is missing). */
    public static function storedPath(array $file): ?string
    {
        $path = $file['path'] ?? null;

        return $path && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /** The sample licence sheet that stands in for a missing file (only when config wakata.placeholder_pdfs is on). */
    public static function placeholder(array $file, Customer $customer): ?string
    {
        if (! config('wakata.placeholder_pdfs')) {
            return null;
        }

        return SimplePdf::sheet(
            'WAKATA Stationers',
            "Wakiso Kampala Teachers' Association",
            $file['title'],
            array_filter([
                trim(($file['level'] ?? '') . ' ' . ($file['year'] ?? '')),
                '',
                'Sample file. The real paper has not been uploaded yet.',
                '',
                'Licensed to: ' . $customer->name . ' (' . $customer->email . ')',
                'Order: ' . $file['order'],
            ], fn ($l) => $l !== null),
        );
    }

    /** @param  Collection<int, Order>  $orders */
    private static function describe(Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        $items = $orders->flatMap(fn (Order $o) => $o->items->map(fn (OrderItem $i) => [$o, $i]));

        // The stored file of each product, in three small queries
        $papers   = Paper::whereIn('id', $items->filter(fn ($p) => $p[1]->item_type === 'paper')->pluck(1)->pluck('item_id')->filter())->pluck('pdf_path', 'id');
        $sets     = ExamSet::whereIn('id', $items->filter(fn ($p) => $p[1]->item_type === 'set')->pluck(1)->pluck('item_id')->filter())->pluck('guide_path', 'id');
        $packages = HolidayPackage::whereIn('id', $items->filter(fn ($p) => $p[1]->item_type === 'pack')->pluck(1)->pluck('item_id')->filter())->pluck('timetable_path', 'id');

        $used = FileDownload::whereIn('order_id', $orders->pluck('id'))->get()->keyBy(fn ($d) => $d->order_id . '|' . $d->file_key);

        $files = collect();

        foreach ($items as [$order, $item]) {
            $ts    = $order->created_at->getTimestampMs();
            $level = strtoupper((string) $item->level);
            $base  = ['order' => $order->number, 'order_id' => $order->id, 'ts' => $ts, 'level' => $level, 'subject' => $item->subject ?: 'All subjects'];

            if ($item->item_type === 'paper' && $item->format === 'pdf') {
                $files->push($base + [
                    'key'   => 'i' . $item->id, 'kind' => 'paper', 'title' => $item->title, 'year' => $item->year,
                    'name'  => Str::slug((string) $item->subject) . '-paper' . ($item->paper_no ?: '') . '-' . $item->year . '.pdf',
                    'path'  => $papers[$item->item_id] ?? null,
                ]);
            } elseif ($item->item_type === 'set' && in_array($order->status, ['shipped', 'completed'], true)) {
                $path = $sets[$item->item_id] ?? null;
                if ($path || config('wakata.placeholder_pdfs')) {
                    $files->push($base + [
                        'key'  => 'i' . $item->id . '-guide', 'kind' => 'guide', 'title' => 'Marking guides: ' . $item->title, 'year' => 2026,
                        'name' => Str::slug($item->title) . '-marking-guides.pdf', 'path' => $path,
                    ]);
                }
            } elseif ($item->item_type === 'pack' && $order->status === 'completed') {
                $path = $packages[$item->item_id] ?? null;
                if ($path || config('wakata.placeholder_pdfs')) {
                    $files->push($base + [
                        'key'  => 'i' . $item->id . '-timetable', 'kind' => 'guide', 'title' => 'Revision timetable: ' . $item->title, 'year' => 2026,
                        'name' => Str::slug($item->title) . '-timetable.pdf', 'path' => $path,
                    ]);
                }
            }
        }

        return $files->map(function (array $f) use ($used) {
            $row  = $used->get($f['order_id'] . '|' . $f['key']);
            $real = self::storedPath($f);

            return $f + [
                'id'    => $f['order'] . ':' . $f['key'],
                'used'  => $row?->used ?? 0,
                'max'   => $row?->max_downloads ?? FileDownload::limit(),
                'size'  => $real ? max(0.1, round(Storage::disk(self::DISK)->size($real) / 1048576, 1)) : null,
                'ready' => $real !== null || (bool) config('wakata.placeholder_pdfs'),
            ];
        })->sortByDesc('ts')->values();
    }
}
