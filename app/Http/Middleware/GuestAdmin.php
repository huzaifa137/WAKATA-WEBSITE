<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sign-in pages for admins who are not signed in yet; signed-in admins go to the dashboard. */
class GuestAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('LoggedAdmin')) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
