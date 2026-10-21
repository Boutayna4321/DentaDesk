<?php

namespace App\Http\Controllers;

use App\Booking\OpeningHours;
use App\Models\Faq;
use App\Models\Practitioner;
use App\Models\Service;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function __invoke(): View
    {
        return view('site.home', [
            'services' => Service::orderBy('position')->get(),
            'faqs' => Faq::where('is_active', true)->get(),
            'hours' => OpeningHours::rows(Practitioner::firstOrFail()),
            'cabinet' => config('cabinet'),
        ]);
    }
}
