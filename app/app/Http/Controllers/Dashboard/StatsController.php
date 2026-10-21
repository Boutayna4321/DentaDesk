<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Reports\PracticeStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = array_key_exists($request->query('p'), PracticeStats::PERIODS) ? $request->query('p') : '30j';
        $s = new PracticeStats($period);

        return view('dashboard.stats', [
            'periods' => PracticeStats::PERIODS, 'period' => $period, 's' => $s,
            'money' => $s->money(), 'occupancy' => $s->occupancy(), 'patients' => $s->patients(),
            'assistant' => $s->assistant(), 'sources' => $s->sources(), 'services' => $s->topServices(), 'weekly' => $s->weekly(),
        ]);
    }
}
