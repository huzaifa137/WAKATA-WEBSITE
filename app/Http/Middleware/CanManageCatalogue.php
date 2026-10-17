<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Changing prices, stock, files or the catalogue needs a role that may do it (see Admin::canManageCatalogue). Runs after admin.auth. */
class CanManageCatalogue
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->attributes->get('admin');

        if (! $admin || ! $admin->canManageCatalogue()) {
            $message = 'Your role can look at the catalogue but not change it.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->back()->with('fail', $message);
        }

        return $next($request);
    }
}
