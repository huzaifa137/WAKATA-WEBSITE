<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sign-in and register pages for customers who are not signed in yet. */
class GuestCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('LoggedCustomer')) {
            return redirect()->route('account.dashboard');
        }

        return $next($request);
    }
}
