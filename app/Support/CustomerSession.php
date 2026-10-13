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
 * Suspended or deleted customers count as signed out, and so does a session that was started before the customer
 * pressed "sign out everywhere else" or changed their password (session('LoggedCustomerEpoch') is older than
 * customers.session_epoch). The answer is remembered for the rest of the request.
 */
class CustomerSession
{
    private const COOKIE = 'wk_customer_remember';
    private const ATTR   = 'wk.customer';

    /** Sign a customer in on this browser: new session id, id and epoch stored. */
    public static function start(Request $request, Customer $customer): void
    {
        $request->session()->regenerate();
        $request->session()->put('LoggedCustomer', $customer->id);
        $request->session()->put('LoggedCustomerEpoch', (int) $customer->session_epoch);
        $request->attributes->remove(self::ATTR);
    }

    /** Sign everything else out: other browsers' sessions and "keep me signed in" cookies stop working. */
    public static function endOtherSessions(Request $request, Customer $customer): void
    {
        $customer->forceFill(['session_epoch' => (int) $customer->session_epoch + 1, 'remember_token' => null])->save();

        if ($request->hasSession() && (int) $request->session()->get('LoggedCustomer') === $customer->id) {
            $request->session()->put('LoggedCustomerEpoch', (int) $customer->session_epoch);
        }
        $request->attributes->remove(self::ATTR);
    }

    public static function resolve(Request $request): ?Customer
    {
        if ($request->attributes->has(self::ATTR)) {
            return $request->attributes->get(self::ATTR) ?: null;
        }

        $customer = null;

        if ($request->hasSession()) {
            $id       = $request->session()->get('LoggedCustomer');
            $customer = $id ? Customer::find($id) : null;

            // A session older than the last "sign out everywhere" no longer counts (a missing epoch means 0)
            if ($customer && (int) $request->session()->get('LoggedCustomerEpoch', 0) !== (int) $customer->session_epoch) {
                $request->session()->forget(['LoggedCustomer', 'LoggedCustomerEpoch']);
                $customer = null;
            }

            if (! $customer) {
                $customer = RememberLogin::restore(Customer::class, self::COOKIE, $request);
                if ($customer && $customer->isActive()) {
                    self::start($request, $customer);
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
