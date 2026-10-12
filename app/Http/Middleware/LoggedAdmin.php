<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Support\RememberLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages need session('LoggedAdmin') (the admin's id).
 * A locked screen (session('AdminLocked')) only lets the unlock and sign-out routes through.
 */
class LoggedAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $this->resolve($request);

        if (! $admin) {
            $request->session()->forget(['LoggedAdmin', 'AdminLocked']);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please sign in to continue.'], 401);
            }
            if ($request->isMethod('GET')) {
                $request->session()->put('admin.intended', $request->fullUrl());
            }

            return redirect()->route('admin.login')->with('fail', 'Please sign in to continue.');
        }

        if ($request->session()->get('AdminLocked') && ! $request->routeIs('admin.locked', 'admin.unlock', 'admin.logout')) {
            return redirect()->route('admin.locked');
        }

        $request->attributes->set('admin', $admin);
        View::share('authAdmin', $admin);

        $response = $next($request);

        // Keep signed-in pages out of the browser cache so Back does not show them after sign-out.
        $response->headers->set('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function resolve(Request $request): ?Admin
    {
        $id = $request->session()->get('LoggedAdmin');
        $admin = $id ? Admin::find($id) : null;

        if (! $admin) {
            $admin = RememberLogin::restore(Admin::class, 'wk_admin_remember', $request);
            if ($admin) {
                $request->session()->regenerate();
                $request->session()->put('LoggedAdmin', $admin->id);
            }
        }

        return $admin && $admin->isActive() ? $admin : null;
    }
}
