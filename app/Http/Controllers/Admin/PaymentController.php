<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Support\AdminSales;
use App\Support\OrderFlow;
use App\Support\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Payments (module 8): confirm and refund payments, the payment methods on the website. Changing needs admin.can:payments. */
class PaymentController extends SalesController
{
    public function index()
    {
        return view('admin.payments');
    }

    /** "Verify" / "Mark payment received". */
    public function verify(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:60']]);

        return $this->act($order, 'Payment marked as received.', fn () => OrderFlow::markPaid($order, $this->admin($request), $data['reference'] ?? null));
    }

    public function refund(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->act($order, 'Refund recorded.', fn () => OrderFlow::refund($order, $this->admin($request), $data['reason'] ?? null));
    }

    /** The four switches under "Payment methods on the website". */
    public function methods(Request $request): JsonResponse
    {
        $keys  = array_keys(config('wakata.payments_base'));
        $rules = [];
        foreach ($keys as $k) {
            $rules[$k] = ['required', 'boolean'];
        }
        $flags = $request->validate($rules, ['*.required' => 'Something went wrong with the switches. Refresh the page.']);

        if (! in_array(true, array_map('boolval', $flags), true)) {
            throw ValidationException::withMessages(['mtn' => 'Keep at least one payment method on, or customers cannot check out.']);
        }
        if (empty($flags['mtn']) && empty($flags['airtel']) && empty($flags['card']) && ! config('wakata.delivery_all.pickup.enabled')) {
            throw ValidationException::withMessages(['cash' => 'Cash is only for pickup, and pickup is switched off. Turn on another payment method.']);
        }

        ShopSettings::savePayments($flags);

        return response()->json(['ok' => true, 'message' => 'Payment methods saved.', 'payments' => AdminSales::payload($this->admin($request))['payments']]);
    }

    /** "Reconcile": lists the mobile money and card payments still waiting to be checked against the statements. No gateway is connected, so staff confirm each one. */
    public function reconcile(): JsonResponse
    {
        $waiting = Order::query()->where('payment_status', 'pending')->whereNotIn('status', ['cancelled', 'refunded'])->where('payment_method', '!=', 'cash')->get(['number', 'payment_method', 'total']);

        return response()->json([
            'ok'      => true,
            'count'   => $waiting->count(),
            'total'   => (int) $waiting->sum('total'),
            'message' => $waiting->isEmpty()
                ? 'Nothing to reconcile: no mobile money or card payment is waiting.'
                : $waiting->count() . ' payment(s) worth UGX ' . number_format($waiting->sum('total')) . ' are waiting. Check each against your MTN, Airtel or card statement, then press Verify.',
        ]);
    }
}
