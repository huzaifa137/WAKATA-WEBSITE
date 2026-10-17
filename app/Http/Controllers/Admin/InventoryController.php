<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Printed stock: the Adjust dialog and the bulk "alert level" action on the Inventory page. */
class InventoryController extends Controller
{
    public function index()
    {
        return view('admin.inventory');
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind'   => ['required', Rule::in(Inventory::KINDS)],
            'id'     => ['required', 'string', 'max:120'],
            'mode'   => ['required', Rule::in(['add', 'remove', 'set'])],
            'qty'    => ['required', 'integer', 'min:0', 'max:10000000'],
            'reason' => ['required', Rule::in(['print', 'count', 'damage', 'return', 'other'])],
            'note'   => ['nullable', 'string', 'max:200'],
        ], [
            'qty.required' => 'Enter a quantity.',
            'qty.integer'  => 'The quantity must be a whole number.',
        ]);

        if ($data['mode'] !== 'set' && (int) $data['qty'] < 1) {
            return response()->json(['message' => 'Enter a quantity of 1 or more.', 'errors' => ['qty' => ['Enter a quantity of 1 or more.']]], 422);
        }

        $item = Inventory::find($data['kind'], $data['id']);
        if (! $item) {
            return response()->json(['message' => 'That item does not exist any more.'], 404);
        }

        $result = Inventory::adjust($item, $data['kind'], $data['mode'], (int) $data['qty'], $data['reason'], $data['note'] ?? null, $request->attributes->get('admin'));

        return response()->json($result + ['name' => Inventory::nameOf($item)]);
    }

    /** Set the low-stock alert level of the selected items. `items` are "kind:slug". */
    public function alert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'   => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['string', 'max:130'],
            'level'   => ['required', 'integer', 'min:0', 'max:100000'],
        ], ['level.required' => 'Enter the alert level.']);

        $n = 0;
        foreach ($data['items'] as $ref) {
            [$kind, $slug] = array_pad(explode(':', $ref, 2), 2, '');
            if ($item = Inventory::find($kind, $slug)) {
                $item->update(['low_stock_at' => (int) $data['level']]);
                $n++;
            }
        }

        return response()->json(['updated' => $n, 'level' => (int) $data['level']]);
    }
}
