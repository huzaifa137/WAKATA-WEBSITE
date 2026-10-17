<?php

namespace App\Http\Controllers\Admin;

use App\Models\Book;
use App\Support\AdminCatalogue;
use App\Support\Catalogue;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin catalogue: books and workbooks (list, add or edit form, delete, duplicate, bulk actions). */
class BookController extends CatalogueController
{
    public function index()
    {
        return view('admin.books', ['badges' => AdminCatalogue::badges()]);
    }

    public function form(Request $request)
    {
        $record = null;
        if ($id = $request->query('id')) {
            $record = Book::where('slug', $id)->first();
            if (! $record) {
                return redirect()->route('admin.books')->with('fail', 'That book is not in the catalogue any more.');
            }
        }

        return view('admin.book-form', [
            'record' => $record,
            'subjects' => \App\Models\Subject::orderBy('sort_order')->orderBy('id')->pluck('name'),
            'levels'   => \App\Models\Level::orderBy('sort_order')->orderBy('id')->get(['code', 'label', 'book_code']),
            'types'    => AdminCatalogue::types(),
            'badges'   => AdminCatalogue::badges(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $book = new Book();

        return $this->save($request, $book, 'added');
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        return $this->save($request, $book, 'saved');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $title = $book->title;
        $book->delete();

        return $this->flashBack('admin.books', '“' . $title . '” was deleted.');
    }

    public function duplicate(Book $book): RedirectResponse
    {
        $copy = $book->replicate(['slug', 'sku', 'featured_order', 'stock']);
        $copy->title      = $book->title . ' (copy)';
        $copy->slug       = $this->uniqueSlug(Book::class, $book->slug . '-copy');
        $copy->sku        = null;                       // the next free SKU
        $copy->status     = 'draft';
        $copy->stock      = 0;
        $copy->sort_order = $this->nextOrder(Book::class);
        $copy->save();

        return redirect()->route('admin.book-form', ['id' => $copy->slug])->with('status', 'A draft copy was created. Check the details and publish it.');
    }

    /** Publish, move to draft, set a badge, adjust prices or delete the selected books. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action'  => ['required', Rule::in(['publish', 'draft', 'badge', 'price', 'delete'])],
            'ids'     => ['required', 'array', 'min:1', 'max:200'],
            'ids.*'   => ['string', 'max:120'],
            'badge'   => ['nullable', 'string', 'max:60'],
            'mode'    => ['nullable', Rule::in(['up', 'down', 'set'])],
            'value'   => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'keepold' => ['nullable'],
        ]);

        $books = Book::whereIn('slug', $data['ids'])->get();
        if ($books->isEmpty()) {
            return back()->with('fail', 'None of the selected books exist any more.');
        }

        if ($data['action'] === 'price') {
            if (! isset($data['mode'], $data['value'])) {
                throw ValidationException::withMessages(['value' => 'Enter the value to apply.']);
            }
            if ($data['mode'] !== 'set' && (float) $data['value'] > 500) {
                throw ValidationException::withMessages(['value' => 'A percentage change above 500% looks like a mistake.']);
            }
        }

        DB::transaction(function () use ($books, $data, $request) {
            foreach ($books as $b) {
                match ($data['action']) {
                    'publish' => $b->update(['status' => 'published']),
                    'draft'   => $b->update(['status' => 'draft']),
                    'badge'   => $b->update(['badge' => $data['badge'] ?: null]),
                    'price'   => $this->reprice($b, $data['mode'], (float) $data['value'], $request->boolean('keepold')),
                    'delete'  => $b->delete(),
                };
            }
        });

        $n = $books->count();
        $what = match ($data['action']) {
            'publish' => 'published', 'draft' => 'moved to draft', 'badge' => 'updated', 'price' => 'repriced', 'delete' => 'deleted',
        };

        return back()->with('status', $n . ($n === 1 ? ' book was ' : ' books were ') . $what . '.');
    }

    private function reprice(Book $b, string $mode, float $value, bool $keepOld): void
    {
        $new = match ($mode) {
            'up'    => (int) (round($b->price * (1 + $value / 100) / 100) * 100),
            'down'  => (int) (round($b->price * (1 - min($value, 100) / 100) / 100) * 100),
            default => (int) round($value),
        };

        $old = $b->old_price;
        if ($keepOld && $new < $b->price) {
            $old = $b->price;                            // the price before the cut is shown struck through
        } elseif ($old !== null && $old <= $new) {
            $old = null;                                 // an old price must stay higher than the price
        }

        $b->update(['price' => $new, 'old_price' => $old]);
    }

    private function save(Request $request, Book $book, string $verb): RedirectResponse
    {
        $id = $book->exists ? $book->id : null;

        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'subject' => ['required', Rule::exists('subjects', 'name')],
            'level'   => ['required', Rule::exists('levels', 'book_code')],
            'type'    => ['nullable', 'string', 'max:60'],
            'edition' => ['nullable', 'string', 'max:60'],
            'pages'   => ['nullable', 'integer', 'min:1', 'max:5000'],
            'price'   => ['required', 'integer', 'min:0', 'max:100000000'],
            'old'     => ['nullable', 'integer', 'gt:price', 'max:100000000'],
            'badge'   => ['nullable', 'string', 'max:60'],
            'desc'    => ['required', 'string', 'max:5000'],
            'img'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'full'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sku'     => ['nullable', 'string', 'max:30', Rule::unique('books', 'sku')->ignore($id)],
            'stock'   => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'low'     => ['nullable', 'integer', 'min:0', 'max:100000'],
            'id'      => ['nullable', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'max:120', Rule::unique('books', 'slug')->ignore($id)],
            'meta'    => ['nullable', 'string', 'max:300'],
        ], [
            'title.required'   => 'Enter the book title.',
            'subject.required' => 'Please choose a subject.',
            'level.required'   => 'Please choose a level.',
            'price.required'   => 'Enter a price.',
            'old.gt'           => 'The old price must be higher than the price.',
            'desc.required'    => 'Write a short description.',
            'id.regex'         => 'Use lower-case letters, numbers and single hyphens only.',
            'id.unique'        => 'Another book already uses this page address.',
            'sku.unique'       => 'Another item already uses this SKU.',
            'img.*'            => 'The cover must be a JPG, PNG or WebP image under 2 MB.',
            'full.*'           => 'The second image must be a JPG, PNG or WebP image under 2 MB.',
        ]);

        $book->fill([
            'title'            => $data['title'],
            'subject_id'       => $this->subjectByName($data['subject'])->id,
            'level_id'         => \App\Models\Level::where('book_code', $data['level'])->firstOrFail()->id,
            'format'           => $data['type'] ?? $book->format ?? 'Textbook',
            'edition'          => $data['edition'] ?? null,
            'pages'            => $data['pages'] ?? null,
            'price'            => $data['price'],
            'old_price'        => $data['old'] ?? null,
            'badge'            => $data['badge'] ?? null,
            'description'      => $data['desc'],
            'meta_description' => $data['meta'] ?? null,
            'status'           => $this->statusFrom($request),
        ]);

        if (! empty($data['sku'])) {
            $book->sku = $data['sku'];
        }
        if (isset($data['low'])) {
            $book->low_stock_at = $data['low'];
        }

        $book->slug = ! empty($data['id']) ? $data['id'] : ($book->slug ?: $this->uniqueSlug(Book::class, $data['title']));

        if (! $book->exists) {
            $book->sort_order = $this->nextOrder(Book::class);
        }
        if ($request->hasFile('img')) {
            $book->cover_image = Media::store($request->file('img'), 'books');
        }
        if ($request->hasFile('full')) {
            $book->back_image = Media::store($request->file('full'), 'books');
        }

        // Featured on the home page: shown after the ones already featured
        if ($request->boolean('featured')) {
            $book->featured_order ??= (int) Book::max('featured_order') + 1;
        } else {
            $book->featured_order = null;
        }

        $wasNew = ! $book->exists;
        $book->save();

        $this->syncStock($book, 'book', isset($data['stock']) ? (int) $data['stock'] : null, $request, $wasNew ? 'Opening stock' : 'Edited on the book form');
        Catalogue::flush();

        $msg = '“' . $book->title . '” was ' . $verb . ($book->status === 'draft' ? ' as a draft (hidden from the website).' : '.');

        return redirect()->route('admin.books')->with('status', $msg);
    }
}
