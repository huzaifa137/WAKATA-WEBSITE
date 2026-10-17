<?php

namespace App\Http\Controllers\Admin;

use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Deliveries (module 8): the board uses the order status route; this saves the "Options and fees" table. Needs admin.can:payments (fees are money). */
class DeliveryController extends SalesController
{
    public function index()
    {
        return view('admin.deliveries');
    }

    public function options(Request $request): JsonResponse
    {
        $rules = [];
        foreach (ShopSettings::EDITABLE_DELIVERY as $k) {
            $rules["$k.label"]   = ['required', 'string', 'max:80'];
            $rules["$k.eta"]     = ['required', 'string', 'max:80'];
            $rules["$k.fee"]     = ['required', 'integer', 'min:0', 'max:500000'];
            $rules["$k.enabled"] = ['required', 'boolean'];
        }

        $rows = $request->validate($rules, [
            '*.label.required' => 'Every option needs a name.',
            '*.eta.required'   => 'Every option needs a time shown to the customer.',
            '*.fee.required'   => 'Enter a fee (0 for free).',
            '*.fee.integer'    => 'The fee must be a whole number of UGX.',
            '*.fee.min'        => 'The fee cannot be negative.',
        ]);

        if (! collect($rows)->contains(fn ($r) => (bool) $r['enabled'])) {
            throw ValidationException::withMessages(['pickup.enabled' => 'Keep at least one delivery option on, or customers cannot order printed items.']);
        }
        $cashOnly = array_keys(config('wakata.payments')) === ['cash'];
        if ($cashOnly && ! $rows['pickup']['enabled']) {
            throw ValidationException::withMessages(['pickup.enabled' => 'Cash is the only payment method on, and it needs pickup. Keep pickup on.']);
        }

        ShopSettings::saveDelivery($rows);

        return response()->json(['ok' => true, 'message' => 'Delivery options saved.', 'delivery' => ShopSettings::delivery()]);
    }
}
