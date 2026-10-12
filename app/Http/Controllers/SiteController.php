<?php

namespace App\Http\Controllers;

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

    public function book()
    {
        return view('site.book');
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
