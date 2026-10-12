<?php

use App\Http\Controllers\Account\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer account (module 2): static views inside one shared layout
|--------------------------------------------------------------------------
| Module 3: sign-in pages sit behind customer.guest, everything else behind
| customer.auth (the custom LoggedCustomer session middleware).
| The pages keep the prototype's file names (order, invoice ...) so the
| prototype JS links keep working; later modules can swap them for resource routes.
*/
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/', fn () => redirect()->route('account.dashboard'))->name('home');

    // Sign-in screens: only for people who are not signed in yet
    Route::middleware('customer.guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.submit');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');
        Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('forgot-password');
        Route::post('/forgot-password', [AuthController::class, 'sendReset'])->name('forgot-password.send');
        Route::get('/reset-password', [AuthController::class, 'showReset'])->name('reset-password');
        Route::post('/reset-password', [AuthController::class, 'reset'])->name('reset-password.update');
    });

    // Sign out works even when the session has expired (the CSRF check still applies)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Everything below needs a signed-in customer
    Route::middleware('customer.auth')->group(function () {
        // Pages that use the sidebar + topbar shell
        Route::view('/dashboard', 'account.dashboard')->name('dashboard');
        Route::view('/orders', 'account.orders')->name('orders');
        Route::view('/order', 'account.order')->name('order');
        Route::view('/library', 'account.library')->name('library');
        Route::view('/invoices', 'account.invoices')->name('invoices');
        Route::view('/invoice', 'account.invoice')->name('invoice');
        Route::view('/saved', 'account.saved')->name('saved');
        Route::view('/addresses', 'account.addresses')->name('addresses');
        Route::view('/support', 'account.support')->name('support');
        Route::view('/profile', 'account.profile')->name('profile');
    });

    // Prototype links (books.html, order.html?id=...) keep working and land on the new URLs.
    Route::get('/{legacy}.html', function (Request $request, string $legacy) {
        $map = [
            'index' => 'account.dashboard',
            'orders' => 'account.orders',
            'order' => 'account.order',
            'library' => 'account.library',
            'invoices' => 'account.invoices',
            'invoice' => 'account.invoice',
            'saved' => 'account.saved',
            'addresses' => 'account.addresses',
            'support' => 'account.support',
            'profile' => 'account.profile',
            'login' => 'account.login',
            'register' => 'account.register',
            'forgot-password' => 'account.forgot-password',
            'reset-password' => 'account.reset-password',
        ];
        $url = route($map[$legacy]);
        $qs  = $request->getQueryString();

        return redirect()->to($qs ? $url . '?' . $qs : $url, 301);
    })->where('legacy', 'index|orders|order|library|invoices|invoice|saved|addresses|support|profile|login|register|forgot-password|reset-password');

    // Anything else under /account shows the account 404 page
    Route::get('/{any}', fn () => response()->view('account.errors.404', [], 404))->where('any', '.*');
});
