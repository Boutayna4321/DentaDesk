<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __invoke(): View
    {
        return view('site.home', [
            'services' => Service::orderBy('position')->get(),
            'cabinet' => config('cabinet'),
        ]);
    }
}
