<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\AuthController;
use App\Http\Controllers\Account\ClaimOrderController;
use App\Http\Controllers\Account\DownloadController;
use App\Http\Controllers\Account\PortalController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SavedController;
use App\Http\Controllers\Account\SupportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer account
|--------------------------------------------------------------------------
| Module 3: sign-in pages sit behind customer.guest, everything else behind
| customer.auth (the custom LoggedCustomer session middleware).
| Module 6: the portal. Pages are drawn from the signed-in customer's own data (WK.portal); the actions
| (download, save, addresses, support, profile) are routes below. Order and invoice pages live at
| /account/orders/WK-10471 and /account/invoices/WK-10471.
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

        // Optional two-step code (module 6)
        Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
        Route::post('/verify', [AuthController::class, 'verify'])->middleware('throttle:20,1')->name('verify.submit');
        Route::post('/verify/resend', [AuthController::class, 'resendOtp'])->name('verify.resend');
    });

    // Sign out works even when the session has expired (the CSRF check still applies)
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Everything below needs a signed-in customer
    Route::middleware('customer.auth')->group(function () {
        Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');

        // Orders and invoices
        Route::get('/orders', [PortalController::class, 'orders'])->name('orders');
        Route::post('/orders/claim', [ClaimOrderController::class, 'send'])->name('orders.claim');
        Route::post('/orders/claim/verify', [ClaimOrderController::class, 'verify'])->middleware('throttle:20,1')->name('orders.claim.verify');
        Route::get('/orders/{number}', [PortalController::class, 'order'])->where('number', 'WK-[0-9]+')->name('order');
        Route::get('/invoices', [PortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{number}', [PortalController::class, 'invoice'])->where('number', 'WK-[0-9]+')->name('invoice');

        // Library: PDF downloads (limited per file) and "email me this file"
        Route::get('/library', [PortalController::class, 'library'])->name('library');
        Route::post('/library/{number}/{file}/download', [DownloadController::class, 'download'])
            ->where(['number' => 'WK-[0-9]+', 'file' => 'i[0-9]+(-guide|-timetable)?'])->middleware('throttle:60,1')->name('library.download');
        Route::post('/library/{number}/{file}/email', [DownloadController::class, 'email'])
            ->where(['number' => 'WK-[0-9]+', 'file' => 'i[0-9]+(-guide|-timetable)?'])->middleware('throttle:10,1')->name('library.email');

        // Saved items
        Route::get('/saved', [SavedController::class, 'index'])->name('saved');
        Route::post('/saved', [SavedController::class, 'store'])->middleware('throttle:60,1')->name('saved.store');
        Route::patch('/saved/{item}', [SavedController::class, 'update'])->whereNumber('item')->name('saved.update');
        Route::delete('/saved/{item}', [SavedController::class, 'destroy'])->whereNumber('item')->name('saved.destroy');

        // Addresses and delivery preferences
        Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
        Route::post('/addresses', [AddressController::class, 'store'])->middleware('throttle:30,1')->name('addresses.store');
        Route::put('/addresses/preferences', [AddressController::class, 'preferences'])->name('addresses.preferences');
        Route::put('/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
        Route::post('/addresses/{address}/default', [AddressController::class, 'makeDefaultAddress'])->whereNumber('address')->name('addresses.default');
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

        // Help and support
        Route::get('/support', [SupportController::class, 'index'])->name('support');
        Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:10,1')->name('support.store');
        Route::post('/support/{ref}/reply', [SupportController::class, 'reply'])->where('ref', 'T-[0-9]+')->middleware('throttle:20,1')->name('support.reply');
        Route::post('/support/{ref}/read', [SupportController::class, 'read'])->where('ref', 'T-[0-9]+')->name('support.read');

        // Profile and settings
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/organisation', [ProfileController::class, 'organisation'])->name('profile.organisation');
        Route::post('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::post('/profile/two-step', [ProfileController::class, 'twoStep'])->name('profile.two-step');
        Route::post('/profile/sign-out-others', [ProfileController::class, 'signOutOthers'])->name('profile.sign-out-others');
        Route::post('/profile/notifications', [ProfileController::class, 'notifications'])->name('profile.notifications');
        Route::post('/profile/payment-numbers', [ProfileController::class, 'paymentNumbers'])->name('profile.payment-numbers');
        Route::get('/profile/export', [ProfileController::class, 'export'])->name('profile.export');
        Route::post('/profile/delete', [ProfileController::class, 'destroy'])->name('profile.delete');

        // Old prototype links: order.html?id=WK-10471, invoice.html?id=WK-10471
        Route::get('/order', [PortalController::class, 'legacy'])->defaults('kind', 'order')->name('legacy.order');
        Route::get('/invoice', [PortalController::class, 'legacy'])->defaults('kind', 'invoice')->name('legacy.invoice');
    });

    // Prototype links (books.html, order.html?id=...) keep working and land on the new URLs.
    Route::get('/{legacy}.html', function (Request $request, string $legacy) {
        $map = [
            'index' => 'account.dashboard',
            'orders' => 'account.orders',
            'order' => 'account.orders',
            'library' => 'account.library',
            'invoices' => 'account.invoices',
            'invoice' => 'account.invoices',
            'saved' => 'account.saved',
            'addresses' => 'account.addresses',
            'support' => 'account.support',
            'profile' => 'account.profile',
            'login' => 'account.login',
            'register' => 'account.register',
            'forgot-password' => 'account.forgot-password',
            'reset-password' => 'account.reset-password',
        ];
        // order.html?id=WK-10471 lands on that order
        if (in_array($legacy, ['order', 'invoice'], true)) {
            return app(PortalController::class)->legacy($request, $legacy);
        }

        $url = route($map[$legacy]);
        $qs  = $request->getQueryString();

        return redirect()->to($qs ? $url . '?' . $qs : $url, 301);
    })->where('legacy', 'index|orders|order|library|invoices|invoice|saved|addresses|support|profile|login|register|forgot-password|reset-password');

    // Anything else under /account shows the account 404 page
    Route::get('/{any}', fn () => response()->view('account.errors.404', [], 404))->where('any', '.*');
});
