<?php

namespace App\Support;

use App\Models\Book;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Level;
use App\Models\Paper;
use App\Models\Subject;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the catalogue the browser needs (the old data.js, now read from the database).
 *
 * The shape is the same one the shop, account and admin scripts already use, so no page had to change:
 * LEVELS, BOOK_LEVELS, SUBJECTS, books, papers, sets, packages, PAPER_IMG, plus `featured` (home page picks).
 * Only published items are included. The database result is cached until any catalogue record changes.
 */
class Catalogue
{
    private const CACHE_KEY = 'wakata.catalogue.v1';

    /** Catalogue as the browser expects it (image paths turned into URLs for this request). */
    public static function frontend(): array
    {
        $data = self::raw();

        foreach ($data['books'] as &$b) {
            foreach (['img', 'full'] as $k) {
                if (isset($b[$k])) {
                    $b[$k] = self::imageUrl($b[$k]);
                }
            }
        }
        unset($b);

        foreach ($data['papers'] as &$p) {
            $p['img'] = self::imageUrl($p['img']);
        }
        unset($p);

        $data['PAPER_IMG'] = self::imageUrl('papers') . '/';

        return $data;
    }

    /** Clear the cached catalogue (called automatically whenever a catalogue record is saved or deleted). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** A path stored in the database (relative to public/assets/img) becomes a full URL. Full URLs pass through. */
    public static function imageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return asset('assets/img/' . ltrim($path, '/'));
    }

    private static function raw(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::build());
    }

    private static function build(): array
    {
        $levels   = Level::orderBy('sort_order')->orderBy('id')->get();
        $subjects = Subject::orderBy('sort_order')->orderBy('id')->get();

        $levelCode = $levels->pluck('code', 'id');          // id => ple
        $bookCode  = $levels->pluck('book_code', 'id');     // id => primary
        $subject   = $subjects->pluck('name', 'id');        // id => Biology

        $LEVELS = $BOOK_LEVELS = [];
        foreach ($levels as $l) {
            $LEVELS[$l->code]            = ['label' => $l->label, 'short' => $l->short, 'book' => $l->book_code, 'full' => $l->full_name];
            $BOOK_LEVELS[$l->book_code]  = $l->book_label;
        }

        // Only subjects that have cover colours; others fall back to the Mathematics colours in the JS.
        $SUBJECTS = [];
        foreach ($subjects as $s) {
            if ($s->color_1 && $s->color_2 && $s->color_3) {
                $SUBJECTS[$s->name] = [$s->color_1, $s->color_2, $s->color_3];
            }
        }

        $books = Book::published()->shopOrder()->get()->map(fn (Book $b) => array_filter([
            'id'      => $b->slug,
            'title'   => $b->title,
            'subject' => $subject[$b->subject_id] ?? '',
            'level'   => $bookCode[$b->level_id] ?? '',
            'price'   => $b->price,
            'old'     => $b->old_price,
            'badge'   => $b->badge,
            'img'     => $b->cover_image,
            'full'    => $b->back_image,
            'pages'   => $b->pages,
            'edition' => $b->edition,
            'type'    => $b->format,
            'desc'    => $b->description,
        ], fn ($v) => $v !== null && $v !== ''));

        // A paper with both formats switched off is not for sale. When only one is off, noPrint / noPdf tell the shop
        // to hide that choice (the keys are absent when a format is on, so the usual payload is unchanged).
        $papers = Paper::published()->shopOrder()->get()->filter(fn (Paper $p) => $p->onSale())->map(fn (Paper $p) => array_filter([
            'id'      => $p->slug,
            'level'   => $levelCode[$p->level_id] ?? '',
            'subject' => $subject[$p->subject_id] ?? '',
            'paper'   => $p->paper,
            'year'    => $p->year,
            'time'    => $p->duration,
            'print'   => $p->print_price,
            'pdf'     => $p->pdf_price,
            'img'     => $p->cover_image,
            'title'   => $p->title,
            'noPrint' => $p->print_on ? null : true,
            'noPdf'   => $p->pdf_on ? null : true,
        ], fn ($v, $k) => ! in_array($k, ['noPrint', 'noPdf'], true) || $v !== null, ARRAY_FILTER_USE_BOTH));

        $sets = ExamSet::published()->shopOrder()->get()->map(fn (ExamSet $s) => [
            'id'       => $s->slug,
            'level'    => $levelCode[$s->level_id] ?? '',
            'title'    => $s->title,
            'year'     => ctype_digit($s->year_label) ? (int) $s->year_label : $s->year_label,
            'price'    => $s->price,
            'worth'    => $s->worth_price,
            'papers'   => $s->papers_count,
            'subjects' => $s->contents ?? [],
            'preview'  => $s->previews ?? [],
            'blurb'    => $s->blurb,
        ]);

        $packages = HolidayPackage::published()->shopOrder()->get()->map(fn (HolidayPackage $k) => array_filter([
            'id'       => $k->slug,
            'level'    => $levelCode[$k->level_id] ?? '',
            'name'     => $k->name,
            'for'      => $k->audience,
            'price'    => $k->price,
            'best'     => $k->is_best ?: null,
            'includes' => $k->includes ?? [],
        ], fn ($v) => $v !== null));

        $featured = fn ($model) => $model::published()->whereNotNull('featured_order')
            ->orderBy('featured_order')->orderBy('id')->pluck('slug')->all();

        return [
            'LEVELS'      => $LEVELS,
            'BOOK_LEVELS' => $BOOK_LEVELS,
            'SUBJECTS'    => $SUBJECTS,
            'books'       => $books->values()->all(),
            'papers'      => $papers->values()->all(),
            'sets'        => $sets->values()->all(),
            'packages'    => $packages->values()->all(),
            'featured'    => [
                'books'    => $featured(Book::class),
                'papers'   => $featured(Paper::class),
                'packages' => $featured(HolidayPackage::class),
            ],
        ];
    }
}
