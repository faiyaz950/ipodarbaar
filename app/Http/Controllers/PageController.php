<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function about()
    {
        return view('pages.about');
    }

    public function disclaimer()
    {
        return view('pages.disclaimer');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function offline()
    {
        return view('pages.offline');
    }
}
