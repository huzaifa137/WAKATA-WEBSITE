<?php

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
