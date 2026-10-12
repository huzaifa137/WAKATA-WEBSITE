<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Support\RememberLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Customer portal pages need session('LoggedCustomer') (the customer's id). */
class LoggedCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $this->resolve($request);

        if (! $customer) {
            $request->session()->forget('LoggedCustomer');

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please sign in to continue.'], 401);
            }
            if ($request->isMethod('GET')) {
                $request->session()->put('account.intended', $request->fullUrl());
            }

            return redirect()->route('account.login')->with('fail', 'Please sign in to continue.');
        }

        $request->attributes->set('customer', $customer);
        View::share('authCustomer', $customer);

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function resolve(Request $request): ?Customer
    {
        $id = $request->session()->get('LoggedCustomer');
        $customer = $id ? Customer::find($id) : null;

        if (! $customer) {
            $customer = RememberLogin::restore(Customer::class, 'wk_customer_remember', $request);
            if ($customer) {
                $request->session()->regenerate();
                $request->session()->put('LoggedCustomer', $customer->id);
            }
        }

        return $customer && $customer->isActive() ? $customer : null;
    }
}
