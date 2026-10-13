<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\ExamSet;
use App\Models\HolidayPackage;
use App\Models\Paper;
use App\Models\SavedItem;
use App\Support\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Saved items ("buy later"): kept per customer in the database, so they follow the customer across devices. */
class SavedController extends Controller
{
    private const MAX = 200;

    public function index(): View
    {
        return view('account.saved');
    }

    /** Save one or more products: { items: [{ type, id, fmt? }] }. Saving the same product twice keeps one row. */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['items' => ['required', 'array', 'min:1', 'max:50']]);

        $customer = $request->attributes->get('customer');
        $saved    = 0;

        foreach ($request->input('items') as $row) {
            $n = Cart::normalise($row);
            if (! $n || ! $this->exists($n['type'], $n['id'])) {
                continue;
            }
            if ($customer->savedItems()->count() >= self::MAX) {
                break;
            }

            $item = SavedItem::firstOrCreate([
                'customer_id' => $customer->id,
                'item_type'   => $n['type'],
                'item_slug'   => $n['id'],
                'format'      => $n['fmt'] ?? '',
            ]);
            $saved += $item->wasRecentlyCreated ? 1 : 0;
        }

        return $this->state($request, $saved ? 201 : 200, $saved);
    }

    /** Switch a saved paper between printed and PDF: { fmt }. */
    public function update(Request $request, SavedItem $item): JsonResponse
    {
        abort_unless($item->customer_id === $request->attributes->get('customer')->id && $item->item_type === 'paper', 404);

        $fmt = $request->validate(['fmt' => ['required', 'in:print,pdf']])['fmt'];

        // Already saved in that format? Then this row is a duplicate: drop it.
        $twin = SavedItem::where('customer_id', $item->customer_id)->where('item_type', 'paper')
            ->where('item_slug', $item->item_slug)->where('format', $fmt)->where('id', '!=', $item->id)->exists();

        $twin ? $item->delete() : $item->update(['format' => $fmt]);

        return $this->state($request);
    }

    public function destroy(Request $request, SavedItem $item): JsonResponse
    {
        abort_unless($item->customer_id === $request->attributes->get('customer')->id, 404);

        $item->delete();

        return $this->state($request);
    }

    private function state(Request $request, int $status = 200, int $saved = 0): JsonResponse
    {
        $list = $request->attributes->get('customer')->savedItems()->orderByDesc('id')->get()->map->toPortal()->all();

        return response()->json(['ok' => true, 'saved' => $list, 'added' => $saved], $status);
    }

    /** Only products that are on sale can be saved. */
    private function exists(string $type, string $slug): bool
    {
        $model = ['book' => Book::class, 'paper' => Paper::class, 'set' => ExamSet::class, 'pack' => HolidayPackage::class][$type] ?? null;

        return $model && $model::published()->where('slug', $slug)->exists();
    }
}
