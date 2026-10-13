<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function home()
    {
        return view('site.home');
    }

    public function books()
    {
        return view('site.books');
    }

    /** /book?id=bio-uce-practical (same link format the prototype used). */
    public function book(Request $request)
    {
        $slug = trim((string) $request->query('id', ''));

        if ($slug === '') {
            return redirect()->route('site.books');
        }

        // Unknown or unpublished books are a real 404 (the prototype quietly showed the first book instead).
        $book = Book::published()->with(['subject', 'level'])->where('slug', $slug)->first();
        abort_if(! $book, 404);

        return view('site.book', ['book' => $book]);
    }

    public function papers()
    {
        return view('site.past-papers');
    }

    public function sets()
    {
        return view('site.exam-sets');
    }

    public function packages()
    {
        return view('site.holiday-packages');
    }

    public function about()
    {
        return view('site.about');
    }

    public function contact()
    {
        return view('site.contact');
    }

    public function cart()
    {
        return view('site.cart');
    }
}
