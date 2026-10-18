<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\SiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website (module 1)
|--------------------------------------------------------------------------
*/
Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/books', [SiteController::class, 'books'])->name('site.books');
Route::get('/book', [SiteController::class, 'book'])->name('site.book');
Route::get('/past-papers', [SiteController::class, 'papers'])->name('site.papers');
Route::get('/exam-sets', [SiteController::class, 'sets'])->name('site.sets');
Route::get('/holiday-packages', [SiteController::class, 'packages'])->name('site.packages');
Route::get('/about', [SiteController::class, 'about'])->name('site.about');
Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');
Route::get('/cart', [SiteController::class, 'cart'])->name('site.cart');

// Contact form and newsletter sign-up (module 9)
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])->middleware('throttle:6,1')->name('contact.store');
Route::post('/newsletter', [\App\Http\Controllers\NewsletterController::class, 'subscribe'])->middleware('throttle:10,1')->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [\App\Http\Controllers\NewsletterController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{40}')->name('newsletter.unsubscribe');

/*
|--------------------------------------------------------------------------
| Cart and checkout (module 5): JSON endpoints used by the shop pages
|--------------------------------------------------------------------------
| The cart lives in the session. Visitors do not need an account to order; signed-in customers get the order
| attached to their account.
*/
Route::prefix('cart')->name('cart.')->middleware('throttle:120,1')->controller(CartController::class)->group(function () {
    Route::post('/items', 'add')->name('add');
    Route::patch('/items', 'update')->name('update');
    Route::delete('/items', 'remove')->name('remove');
    Route::delete('/', 'clear')->name('clear');
});
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

// Old prototype links (index.html, books.html?id=...) keep working and land on the new URLs.
Route::get('/{legacy}.html', function (Request $request, string $legacy) {
    $map = [
        'index' => 'site.home',
        'books' => 'site.books',
        'book' => 'site.book',
        'past-papers' => 'site.papers',
        'exam-sets' => 'site.sets',
        'holiday-packages' => 'site.packages',
        'about' => 'site.about',
        'contact' => 'site.contact',
        'cart' => 'site.cart',
    ];
    $url = route($map[$legacy]);
    $qs  = $request->getQueryString();

    return redirect()->to($qs ? $url . '?' . $qs : $url, 301);
})->where('legacy', 'index|books|book|past-papers|exam-sets|holiday-packages|about|contact|cart');
