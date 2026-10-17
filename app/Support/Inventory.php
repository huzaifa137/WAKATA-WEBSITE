<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Book;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Paper;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Printed stock. Four kinds of item have it: books (books.stock), printed papers (papers.print_stock),
 * exam sets (exam_sets.stock) and holiday packages (holiday_packages.stock). Every change goes through adjust(),
 * which keeps the stock from going below zero and writes a stock_adjustments row.
 */
class Inventory
{
    public const KINDS = ['book', 'paper', 'set', 'pack'];

    /** @return class-string<Model> */
    public static function model(string $kind): string
    {
        return ['book' => Book::class, 'paper' => Paper::class, 'set' => ExamSet::class, 'pack' => HolidayPackage::class][$kind];
    }

    public static function column(string $kind): string
    {
        return $kind === 'paper' ? 'print_stock' : 'stock';
    }

    public static function find(string $kind, string $slug): ?Model
    {
        return in_array($kind, self::KINDS, true) ? self::model($kind)::where('slug', $slug)->first() : null;
    }

    public static function nameOf(Model $m): string
    {
        return match (true) {
            $m instanceof Paper          => $m->title . ' ' . $m->year,
            $m instanceof HolidayPackage => $m->name,
            default                      => $m->title,
        };
    }

    /**
     * @param  string  $mode  add | remove | set
     * @return array{stock:int, delta:int}
     */
    public static function adjust(Model $item, string $kind, string $mode, int $quantity, string $reason, ?string $note, ?Admin $admin): array
    {
        return DB::transaction(function () use ($item, $kind, $mode, $quantity, $reason, $note, $admin) {
            $col = self::column($kind);
            $row = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first() ?? $item;
            $cur = (int) $row->{$col};

            $new = match ($mode) {
                'add'    => $cur + $quantity,
                'remove' => max(0, $cur - $quantity),
                default  => $quantity,
            };

            $row->{$col} = $new;
            $row->save();

            StockAdjustment::create([
                'item_type'   => $kind,
                'item_id'     => $row->getKey(),
                'item_slug'   => $row->slug,
                'item_name'   => self::nameOf($row),
                'mode'        => $mode,
                'quantity'    => $quantity,
                'delta'       => $new - $cur,
                'stock_after' => $new,
                'reason'      => $reason,
                'note'        => $note ?: null,
                'admin_id'    => $admin?->id,
                'admin_name'  => $admin?->name,
            ]);

            return ['stock' => $new, 'delta' => $new - $cur];
        });
    }
}
