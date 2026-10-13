<?php

namespace App\Http\Controllers;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderReceivedStaffMail;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Turns the session cart into an order. Prices, fees and totals are always worked out here, never taken from the browser. */
class CheckoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $cart   = Cart::current();
        $priced = $cart->priced();

        if (! $priced['lines']) {
            return response()->json(['ok' => false, 'message' => 'Your cart is empty.', 'cart' => $priced], 422);
        }

        // Something was unpublished since it was added: show the updated cart instead of charging for it.
        if ($priced['removed'] > 0) {
            return response()->json([
                'ok'      => false,
                'message' => 'Some items are no longer available, so we updated your cart. Please check it and place the order again.',
                'cart'    => $priced,
            ], 409);
        }

        $physical = $priced['physical'];
        $methods  = config('wakata.delivery');
        $pickups  = array_keys(array_diff_key($methods, ['email' => 1]));

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'phone'    => ['required', 'string', 'max:30', function ($attr, $value, $fail) {
                if (strlen(preg_replace('/\D/', '', $value)) < 9) {
                    $fail('Enter a valid phone number.');
                }
            }],
            'email'    => ['required', 'email', 'max:190'],
            'delivery' => [Rule::requiredIf($physical), 'nullable', Rule::in($pickups)],
            'address'  => [Rule::requiredIf($physical && $request->input('delivery') !== 'pickup'), 'nullable', 'string', 'max:500'],
            'pay'      => ['required', Rule::in(array_keys(config('wakata.payments')))],
        ], [
            'name.required'     => 'Enter your name.',
            'phone.required'    => 'Enter a valid phone number.',
            'email.required'    => 'Enter a valid email address.',
            'email.email'       => 'Enter a valid email address.',
            'delivery.required' => 'Choose how you want to receive your order.',
            'delivery.in'       => 'Choose how you want to receive your order.',
            'address.required'  => 'Enter where we should deliver.',
            'pay.required'      => 'Choose a payment method to continue.',
            'pay.in'            => 'Choose a payment method to continue.',
        ]);

        // PDF-only carts are emailed; anything else follows the chosen delivery method.
        $delivery = $physical ? $data['delivery'] : 'email';

        if ($data['pay'] === 'cash' && ! ($physical && $delivery === 'pickup')) {
            return response()->json([
                'ok'      => false,
                'message' => 'Cash is only available when you collect from our shop.',
                'errors'  => ['pay' => ['Cash is only available when you collect from our shop.']],
            ], 422);
        }

        $fee   = (int) config("wakata.delivery.$delivery.fee", 0);
        $total = $priced['subtotal'] + $fee;

        $customerId = $this->signedInCustomerId($request);

        $order = DB::transaction(function () use ($data, $priced, $delivery, $fee, $total, $physical, $customerId, $request) {
            $order = Order::create([
                'customer_id'      => $customerId,
                'customer_name'    => trim($data['name']),
                'customer_email'   => $data['email'],
                'customer_phone'   => trim($data['phone']),
                'delivery_method'  => $delivery,
                'delivery_address' => $physical && $delivery !== 'pickup' ? trim((string) $data['address']) : null,
                'payment_method'   => $data['pay'],
                'payment_status'   => 'pending',
                'status'           => 'pending',
                'subtotal'         => $priced['subtotal'],
                'delivery_fee'     => $fee,
                'total'            => $total,
                'ip_address'       => $request->ip(),
            ]);

            // The number comes from the row id, so two orders can never share one.
            $order->forceFill(['number' => 'WK-' . (Order::NUMBER_BASE + $order->id)])->save();

            foreach ($priced['lines'] as $l) {
                $order->items()->create([
                    'item_type'   => $l['type'],
                    'item_id'     => $l['item_id'],
                    'item_slug'   => $l['id'],
                    'format'      => $l['fmt'],
                    'title'       => $l['title'],
                    'level'       => $l['level'],
                    'subject'     => $l['subject'],
                    'year'        => $l['year'],
                    'paper_no'    => $l['paperNo'],
                    'unit_price'  => $l['price'],
                    'quantity'    => $l['qty'],
                    'line_total'  => $l['line'],
                    'is_physical' => $l['phys'],
                ]);
            }

            return $order;
        });

        $cart->clear();
        $this->notify($order->load('items'));

        return response()->json([
            'ok'      => true,
            'ref'     => $order->number,
            'total'   => $order->total,
            'payName' => $order->payment_label,
            'cart'    => ['lines' => [], 'count' => 0, 'subtotal' => 0, 'physical' => false, 'removed' => 0],
        ], 201);
    }

    /** The order belongs to the signed-in customer. Guests can still order: it just is not tied to an account. */
    private function signedInCustomerId(Request $request): ?int
    {
        $id = $request->session()->get('LoggedCustomer');
        if (! $id) {
            return null;
        }

        $customer = Customer::find($id);

        return $customer && $customer->isActive() ? $customer->id : null;
    }

    /** Emails never block the order: a mail outage is logged and the order stands. */
    private function notify(Order $order): void
    {
        try {
            Mail::to($order->customer_email)->send(new OrderPlacedMail($order));
        } catch (\Throwable $e) {
            report($e);
        }

        if ($staff = config('wakata.order_notify')) {
            try {
                Mail::to($staff)->send(new OrderReceivedStaffMail($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
