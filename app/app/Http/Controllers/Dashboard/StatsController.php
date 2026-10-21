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
        $now = $s->headline();
        $before = $s->previous()->headline();
        $occupancy = $s->occupancy();
        $sources = $s->sources();
        $acts = $s->revenueByAct();
        $outstanding = $s->outstanding();

        return view('dashboard.stats', [
            'periods' => PracticeStats::PERIODS, 'period' => $period, 's' => $s,
            'now' => $now, 'before' => $before, 'outstanding' => $outstanding,
            'occupancy' => $occupancy, 'weekly' => $s->weekly(), 'sources' => $sources,
            'methods' => $s->paymentMethods(), 'acts' => $acts,
            'insights' => $s->insights($now, $before, $occupancy, $sources, $acts, $outstanding),
        ]);
    }
}
