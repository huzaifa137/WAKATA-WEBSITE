<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer account (module 2): static views inside one shared layout
|--------------------------------------------------------------------------
| Module 3 wraps these groups in the custom LoggedCustomer middleware.
| The pages keep the prototype's file names (order, invoice ...) so the
| prototype JS links keep working; later modules can swap them for resource routes.
*/
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/', fn () => redirect()->route('account.dashboard'))->name('home');

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

    // Sign-in screens (no sidebar). Logic arrives in module 3.
    Route::view('/login', 'account.auth.login')->name('login');
    Route::view('/register', 'account.auth.register')->name('register');
    Route::view('/forgot-password', 'account.auth.forgot-password')->name('forgot-password');
    Route::view('/reset-password', 'account.auth.reset-password')->name('reset-password');

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
});
