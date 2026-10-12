<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin console (module 2): static views inside one shared layout
|--------------------------------------------------------------------------
| Module 3: sign-in pages sit behind admin.guest, everything else behind
| admin.auth (the custom LoggedAdmin session middleware).
| The pages keep the prototype's file names (book-form, paper-form ...) so the
| prototype JS links keep working; later modules swap them for resource routes.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');

    // Sign-in screens: only for people who are not signed in yet
    Route::middleware('admin.guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('forgot-password');
        Route::post('/forgot-password', [AuthController::class, 'sendReset'])->name('forgot-password.send');
        Route::get('/reset-password', [AuthController::class, 'showReset'])->name('reset-password');
        Route::post('/reset-password', [AuthController::class, 'reset'])->name('reset-password.update');
        Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
        Route::post('/verify', [AuthController::class, 'verify'])->name('verify.submit');
        Route::post('/verify/resend', [AuthController::class, 'resendOtp'])->name('verify.resend');
    });

    // Sign out works even when the session has expired (the CSRF check still applies)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Everything below needs a signed-in admin
    Route::middleware('admin.auth')->group(function () {
        Route::post('/lock', [AuthController::class, 'lock'])->name('lock');
        Route::get('/locked', [AuthController::class, 'showLocked'])->name('locked');
        Route::post('/unlock', [AuthController::class, 'unlock'])->name('unlock');
        Route::post('/security/two-factor', [AuthController::class, 'twoFactor'])->name('two-factor');

        // Pages that use the sidebar + topbar shell
        Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
        Route::view('/books', 'admin.books')->name('books');
        Route::view('/book-form', 'admin.book-form')->name('book-form');
        Route::view('/past-papers', 'admin.past-papers')->name('past-papers');
        Route::view('/paper-form', 'admin.paper-form')->name('paper-form');
        Route::view('/exam-sets', 'admin.exam-sets')->name('exam-sets');
        Route::view('/set-form', 'admin.set-form')->name('set-form');
        Route::view('/packages', 'admin.packages')->name('packages');
        Route::view('/package-form', 'admin.package-form')->name('package-form');
        Route::view('/taxonomy', 'admin.taxonomy')->name('taxonomy');
        Route::view('/media', 'admin.media')->name('media');
        Route::view('/inventory', 'admin.inventory')->name('inventory');
        Route::view('/orders', 'admin.orders')->name('orders');
        Route::view('/order', 'admin.order')->name('order');
        Route::view('/payments', 'admin.payments')->name('payments');
        Route::view('/deliveries', 'admin.deliveries')->name('deliveries');
        Route::view('/customers', 'admin.customers')->name('customers');
        Route::view('/messages', 'admin.messages')->name('messages');
        Route::view('/subscribers', 'admin.subscribers')->name('subscribers');
        Route::view('/testimonials', 'admin.testimonials')->name('testimonials');
        Route::view('/faqs', 'admin.faqs')->name('faqs');
        Route::view('/content', 'admin.content')->name('content');
        Route::view('/reports', 'admin.reports')->name('reports');
        Route::view('/team', 'admin.team')->name('team');
        Route::view('/activity', 'admin.activity')->name('activity');
        Route::view('/settings', 'admin.settings')->name('settings');
        Route::view('/profile', 'admin.profile')->name('profile');
    });

    // Prototype links (books.html, order.html?id=...) keep working and land on the new URLs.
    Route::get('/{legacy}.html', function (Request $request, string $legacy) {
        $map = [
            'index' => 'admin.dashboard',
            'books' => 'admin.books',
            'book-form' => 'admin.book-form',
            'past-papers' => 'admin.past-papers',
            'paper-form' => 'admin.paper-form',
            'exam-sets' => 'admin.exam-sets',
            'set-form' => 'admin.set-form',
            'packages' => 'admin.packages',
            'package-form' => 'admin.package-form',
            'taxonomy' => 'admin.taxonomy',
            'media' => 'admin.media',
            'inventory' => 'admin.inventory',
            'orders' => 'admin.orders',
            'order' => 'admin.order',
            'payments' => 'admin.payments',
            'deliveries' => 'admin.deliveries',
            'customers' => 'admin.customers',
            'messages' => 'admin.messages',
            'subscribers' => 'admin.subscribers',
            'testimonials' => 'admin.testimonials',
            'faqs' => 'admin.faqs',
            'content' => 'admin.content',
            'reports' => 'admin.reports',
            'team' => 'admin.team',
            'activity' => 'admin.activity',
            'settings' => 'admin.settings',
            'profile' => 'admin.profile',
            'login' => 'admin.login',
            'forgot-password' => 'admin.forgot-password',
            'reset-password' => 'admin.reset-password',
            'verify' => 'admin.verify',
            'locked' => 'admin.locked',
        ];
        $url = route($map[$legacy]);
        $qs  = $request->getQueryString();

        return redirect()->to($qs ? $url . '?' . $qs : $url, 301);
    })->where('legacy', 'index|books|book-form|past-papers|paper-form|exam-sets|set-form|packages|package-form|taxonomy|media|inventory|orders|order|payments|deliveries|customers|messages|subscribers|testimonials|faqs|content|reports|team|activity|settings|profile|login|forgot-password|reset-password|verify|locked');

    // Anything else under /admin shows the admin 404 page
    Route::get('/{any}', fn () => response()->view('admin.errors.404', [], 404))->where('any', '.*');
});
