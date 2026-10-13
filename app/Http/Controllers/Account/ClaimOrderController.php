<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Mail\CustomerOtpMail;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * "Find an order": put orders placed as a guest onto the signed-in account.
 *
 * Nothing is linked just because an email matches (nobody verifies emails at sign-up). Instead the person proves they
 * own the address the order was placed with: we email a 6-digit code to THAT address, and only the right code attaches
 * the order. Because the address is then proven, the customer's other guest orders placed with it come along too.
 * The first step answers the same way whether or not the order exists, so it cannot be used to look orders up.
 */
class ClaimOrderController extends Controller
{
    private const TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function send(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        $number   = $this->number($request);

        if (! $number) {
            return response()->json(['ok' => false, 'message' => 'Enter an order number like WK-10471.'], 422);
        }

        $key = 'claim-send|' . $customer->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['ok' => false, 'message' => 'Too many tries. Please wait a few minutes and try again.'], 429);
        }
        RateLimiter::hit($key, 600);

        $order = Order::where('number', $number)->first();

        if ($order && $order->customer_id === $customer->id) {
            return response()->json(['ok' => true, 'done' => true, 'message' => 'That order is already in your account.']);
        }

        if ($order && $order->customer_id === null) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            Cache::put($this->cacheKey($customer->id, $number), hash('sha256', $code), now()->addMinutes(self::TTL_MINUTES));
            Cache::forget($this->cacheKey($customer->id, $number) . '|tries');

            try {
                Mail::to($order->customer_email)->send(new CustomerOtpMail($order->customer_name, $code, 'order'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // The same words either way
        return response()->json([
            'ok'      => true,
            'message' => 'If that order number is right and it was placed without an account, we have emailed a 6-digit code to the email address used at checkout.',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $customer = $request->attributes->get('customer');
        $number   = $this->number($request);
        $code     = preg_replace('/\D/', '', (string) $request->input('code'));

        if (! $number || strlen($code) !== 6) {
            return response()->json(['ok' => false, 'message' => 'Enter the order number and the 6-digit code.'], 422);
        }

        $key   = $this->cacheKey($customer->id, $number);
        $tries = (int) Cache::get($key . '|tries', 0);
        $hash  = Cache::get($key);

        if ($tries >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return response()->json(['ok' => false, 'message' => 'Too many wrong codes. Ask for a new code.'], 429);
        }

        if (! $hash || ! hash_equals($hash, hash('sha256', $code))) {
            Cache::put($key . '|tries', $tries + 1, now()->addMinutes(self::TTL_MINUTES));

            return response()->json(['ok' => false, 'message' => 'That code is not right, or it has expired.'], 422);
        }

        $order = Order::where('number', $number)->whereNull('customer_id')->first();
        if (! $order) {
            Cache::forget($key);

            return response()->json(['ok' => false, 'message' => 'That code is not right, or it has expired.'], 422);
        }

        // The code went to this address, so the person owns it: bring all their guest orders over.
        $linked = Order::query()
            ->whereNull('customer_id')
            ->where('customer_email', $order->customer_email)
            ->get()
            ->each(function (Order $o) use ($customer) {
                $o->forceFill(['customer_id' => $customer->id])->save();
                $o->record('Added to your account', 'Verified by email code');
            });

        Cache::forget($key);
        Cache::forget($key . '|tries');

        $n = $linked->count();

        return response()->json([
            'ok'      => true,
            'count'   => $n,
            'message' => $n === 1 ? 'Order ' . $order->number . ' is now in your account.' : $n . ' orders (including ' . $order->number . ') are now in your account.',
        ]);
    }

    private function number(Request $request): ?string
    {
        $n = Str::upper(trim((string) $request->input('number')));

        return preg_match('/^WK-\d{3,12}$/', $n) ? $n : null;
    }

    private function cacheKey(int $customerId, string $number): string
    {
        return 'claim-order|' . $customerId . '|' . $number;
    }
}
