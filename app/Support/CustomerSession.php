<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * Who is the signed-in customer on this request? One place for the rule, used by the customer.auth middleware,
 * the public header (WK.user) and checkout.
 *
 *   1. session('LoggedCustomer') holds the customer id, or
 *   2. a valid "keep me signed in" cookie signs them back in (the session id is renewed, like a normal sign-in).
 * Suspended or deleted customers count as signed out. The answer is remembered for the rest of the request.
 */
class CustomerSession
{
    private const COOKIE = 'wk_customer_remember';
    private const ATTR   = 'wk.customer';

    public static function resolve(Request $request): ?Customer
    {
        if ($request->attributes->has(self::ATTR)) {
            return $request->attributes->get(self::ATTR) ?: null;
        }

        $customer = null;

        if ($request->hasSession()) {
            $id       = $request->session()->get('LoggedCustomer');
            $customer = $id ? Customer::find($id) : null;

            if (! $customer) {
                $customer = RememberLogin::restore(Customer::class, self::COOKIE, $request);
                if ($customer && $customer->isActive()) {
                    $request->session()->regenerate();
                    $request->session()->put('LoggedCustomer', $customer->id);
                }
            }

            if ($customer && ! $customer->isActive()) {
                $customer = null;
            }
        }

        $request->attributes->set(self::ATTR, $customer ?: false);

        return $customer;
    }
}
