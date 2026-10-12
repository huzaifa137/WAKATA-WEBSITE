<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Custom session auth (LoggedAdmin / LoggedCustomer), same idea as SMASA
        $middleware->alias([
            'admin.auth'     => \App\Http\Middleware\LoggedAdmin::class,
            'admin.guest'    => \App\Http\Middleware\GuestAdmin::class,
            'customer.auth'  => \App\Http\Middleware\LoggedCustomer::class,
            'customer.guest' => \App\Http\Middleware\GuestCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired session on a form post: send people back to the right sign-in page instead of a 419 screen.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Refresh the page and try again.'], 419);
            }

            $to = $request->is('admin', 'admin/*') ? route('admin.login')
                : ($request->is('account', 'account/*') ? route('account.login') : url('/'));

            return redirect($to)->with('fail', 'Your session expired. Please try again.');
        });
    })->create();
