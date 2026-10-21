@extends('layouts.dashboard')
@section('title', 'Statistiques')
@use('App\Billing\Money')
@php
    $days = [1 => ['Lun', 'Lundi'], ['Mar', 'Mardi'], ['Mer', 'Mercredi'], ['Jeu', 'Jeudi'], ['Ven', 'Vendredi'], ['Sam', 'Samedi'], ['Dim', 'Dimanche']];
    $hours = collect($occupancy['heat'])->flatMap(fn ($h) => array_keys($h))->unique()->sort()->values();
    // [title, value now, value before, format, higher is better, weekly series key]
    $tiles = [
        ['Encaissé', $now['collected'], $before['collected'], 'money', true, 'collected'],
        ['Panier moyen', $now['ticket'], $before['ticket'], 'money', true, 'ticket'],
        ['Taux de remplissage', $now['occupancy'], $before['occupancy'], 'pct', true, 'occupancy'],
        ['Nouveaux patients', $now['newPatients'], $before['newPatients'], 'int', true, 'newPatients'],
        ['Taux d’absence', $now['noShowRate'], $before['noShowRate'], 'pct', false, 'noShowRate'],
        ['RDV pris par l’assistant', $now['assistantBookings'], $before['assistantBookings'], 'int', true, 'assistant'],
    ];
    $show = fn ($v, $f) => $v === null ? '—' : match ($f) { 'money' => number_format($v / 100, 0, ',', "\u{00A0}").' DH', 'pct' => round($v * 100).' %', default => (string) $v };
    $chart = [
        'labels' => $weekly->pluck('label'),
        'note' => $weekly->map(fn ($w) => $w['partial'] ? 'semaine en cours' : null),
        'series' => [
            ['name' => 'Encaissé', 'values' => $weekly->pluck('collected'), 'color' => '#1f56d6', 'area' => true, 'label' => true],
            ['name' => 'Facturé', 'values' => $weekly->pluck('billed'), 'color' => '#98a3b8', 'dash' => true],
        ],
    ];
    $heat = [
        'rows' => collect($occupancy['heat'])->keys()->sort()->values()->map(fn ($d) => ['key' => $d, 'label' => $days[$d][0], 'full' => $days[$d][1]]),
        'cols' => $hours, 'values' => $occupancy['heat'],
    ];
@endphp

@section('content')
<div class="top">
    <h1>Statistiques<small>Du {{ $s->from->translatedFormat('j F') }} au {{ $s->to->translatedFormat('j F Y') }} · les flèches comparent à la période précédente de même durée</small></h1>
    <div class="btn-group btn-group-sm" role="group" aria-label="Période">
        @foreach ($periods as $k => $v)<a class="btn {{ $period === $k ? 'btn-dark' : 'btn-outline-secondary' }}" href="?p={{ $k }}">{{ $v }}</a>@endforeach
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="box insight-box h-100">
            <div class="hd">À retenir</div>
            <ul class="insights">
                @foreach ($insights as $i)
                    <li class="{{ $i['tone'] }}"><span class="ic" aria-hidden="true">{{ ['good' => '▲', 'bad' => '!', 'neutral' => '●'][$i['tone']] }}</span>{{ $i['text'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="kpi-grid">
            @foreach ($tiles as [$title, $v, $b, $f, $upGood, $key])
                @php
                    $delta = ($v !== null && $b) ? ($v - $b) / $b : null;
                    $good = $delta === null ? null : (($delta >= 0) === $upGood);
                @endphp
                <div>
                    <div class="kpi2">
                        <span class="t">{{ $title }}</span>
                        <b>{{ $show($v, $f) }}</b>
                        @if ($delta !== null && abs($delta) >= 0.005)
                            <span class="delta {{ $good ? 'up' : 'down' }}">{{ $delta >= 0 ? '▲' : '▼' }} {{ abs(round($delta * 100)) }} %<small> vs avant</small></span>
                        @else
                            <span class="delta flat">= stable</span>
                        @endif
                        @if ($key)<div class="spark" data-spark='@json($weekly->pluck($key))'></div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-xl-8">
        <div class="box h-100">
            <div class="hd">Encaissé et facturé par semaine <span class="legend-inline"><i style="background:#1f56d6"></i>Encaissé <i class="dash"></i>Facturé</span></div>
            <div class="p-3">
                <div data-trend='@json($chart)' role="img" aria-label="Encaissé et facturé par semaine sur 12 semaines"></div>
                <details class="small mt-2"><summary class="text-mute">Voir les données</summary>
                    <table class="table table-sm small mb-0 mt-2"><thead><tr><th>Semaine du</th><th class="text-end">Facturé</th><th class="text-end">Encaissé</th><th class="text-end">Patients vus</th></tr></thead><tbody>
                        @foreach ($weekly as $w)<tr><td>{{ $w['label'] }}{{ $w['partial'] ? ' (en cours)' : '' }}</td><td class="text-end">{{ Money::format($w['billed']) }}</td><td class="text-end">{{ Money::format($w['collected']) }}</td><td class="text-end">{{ $w['visits'] }}</td></tr>@endforeach
                    </tbody></table>
                </details>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="box h-100">
            <div class="hd">Encaissé et reste à encaisser <span>sur la période</span></div>
            <div class="p-3">
                @php
                    $donut = $methods->filter(fn ($m) => $m['value'] > 0)->values()->push(['label' => 'Reste à encaisser', 'value' => $outstanding['amount'], 'muted' => true]);
                @endphp
                <div data-donut='@json($donut)' data-title="Facturé"></div>
                <p class="text-mute small mt-3 mb-2"><b class="text-danger">{{ Money::format($outstanding['amount']) }}</b> à encaisser sur {{ $outstanding['count'] }} facture(s) ouverte(s). Passez la souris sur le cercle pour le détail.</p>
                <a class="btn btn-outline-primary btn-sm" href="{{ route('dash.documents', ['tab' => 'collect']) }}">Voir les factures à encaisser</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-xl-7">
        <div class="box h-100">
            <div class="hd">Quand le fauteuil est occupé <span>part du temps d’ouverture réservée, par heure</span></div>
            <div class="p-3">
                <div data-heat='@json($heat)'></div>
                <div class="heat-legend"><span>0 %</span><i></i><span>100 %</span><span class="ms-3"><i class="closed"></i>fermé</span></div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="box h-100">
            <div class="hd">D’où viennent les rendez-vous</div>
            <div class="p-3"><div data-rings='@json($sources)'></div></div>
        </div>
    </div>
</div>

<div class="box mt-3">
    <div class="hd">Ce qui rapporte <span>montant facturé par acte sur la période</span></div>
    @php
        $top = $acts->take(8);
        $tree = $top->when($acts->count() > 8, fn ($c) => $c->push(['label' => 'Autres actes', 'value' => $acts->slice(8)->sum('value'), 'count' => $acts->slice(8)->sum('count'), 'muted' => true]));
    @endphp
    <div class="p-3"><div data-treemap='@json($tree->values())'></div></div>
</div>
@endsection
