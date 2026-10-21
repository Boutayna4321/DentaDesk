@extends('layouts.dashboard')
@section('title', 'Statistiques')
@use('App\Billing\Money')
@php
    $pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 0).' %';
    $dh = fn ($c) => number_format($c / 100, 0, ',', "\u{00A0}").' DH';
    $compact = fn ($c) => $c >= 100000 ? rtrim(rtrim(number_format($c / 100000, 1, ',', ''), '0'), ',').'K' : number_format($c / 100, 0);
    // Clean axis: round the max up to 1, 2 or 5 × 10^n.
    $weeklyMax = max(1, $weekly->max('value'));
    $mag = 10 ** floor(log10($weeklyMax));
    $niceMax = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($v) => $v >= $weeklyMax);
    $ticks = [0, $niceMax / 2, $niceMax];
    $peak = $weekly->sortByDesc('value')->keys()->first();
    $days = [1 => 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
@endphp

@section('content')
<div class="top">
    <h1>Statistiques<small>Du {{ $s->from->translatedFormat('j F') }} au {{ $s->to->translatedFormat('j F Y') }}</small></h1>
    <div class="btn-group btn-group-sm" role="group" aria-label="Période">
        @foreach ($periods as $k => $v)<a class="btn {{ $period === $k ? 'btn-dark' : 'btn-outline-secondary' }}" href="?p={{ $k }}">{{ $v }}</a>@endforeach
    </div>
</div>

<div class="row g-3">
    <div class="col-6 col-xl-3"><div class="kpi"><span>Encaissé</span><b>{{ $dh($money['collected']) }}</b><span>sur {{ $dh($money['billed']) }} facturés</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Reste à encaisser</span><b>{{ $dh($money['outstanding']) }}</b><i class="{{ $money['outstandingCount'] ? 'down' : '' }}">{{ $money['outstandingCount'] }} facture(s) ouverte(s)</i></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Panier moyen</span><b>{{ $money['invoices'] ? $dh(intdiv($money['billed'], $money['invoices'])) : '—' }}</b><span>{{ $money['invoices'] }} factures</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Taux de remplissage</span><b>{{ $pct($occupancy['rate']) }}</b><span>temps réservé / temps d’ouverture</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Taux d’absence</span><b>{{ $pct($patients['noShowRate']) }}</b><span>absents / (venus + absents)</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Nouveaux patients</span><b>{{ $patients['new'] }}</b><span>{{ $patients['total'] }} dossiers au total</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Délai moyen avant RDV</span><b>{{ $patients['leadDays'] === null ? '—' : number_format($patients['leadDays'], 1, ',', '').' j' }}</b><span>entre la prise et le rendez-vous</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Assistant : conversations → RDV</span><b>{{ $pct($assistant['rate']) }}</b><span>{{ $assistant['booked'] }} RDV sur {{ $assistant['conversations'] }} conversations</span></div></div>
</div>

<div class="row g-3 mt-0">
    <div class="col-xl-7">
        <div class="box h-100">
            <div class="hd">Encaissé par semaine <span>12 dernières semaines, quelle que soit la période</span></div>
            <div class="p-3">
                <div class="colchart" role="img" aria-label="Montant encaissé par semaine sur 12 semaines">
                    <div class="axis">@foreach (array_reverse($ticks) as $t)<span>{{ $compact($t) }}</span>@endforeach</div>
                    <div class="plot">
                        @foreach ($ticks as $t)<i class="grid" style="bottom: {{ $t / $niceMax * 100 }}%"></i>@endforeach
                        @foreach ($weekly as $i => $w)
                            @php $isLast = $loop->last; $label = $isLast || $i === $peak; @endphp
                            <div class="col" data-tip="Semaine du {{ $w['week']->translatedFormat('j M') }} · {{ $dh($w['value']) }} · {{ $w['count'] }} paiement(s)" tabindex="0">
                                @if ($label && $w['value'])<em style="bottom: calc({{ $w['value'] / $niceMax * 100 }}% + 4px)">{{ $compact($w['value']) }}</em>@endif
                                <b style="height: {{ $w['value'] / $niceMax * 100 }}%"></b>
                                <small>{{ $w['week']->format('d/m') }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
                <details class="small mt-2"><summary class="text-mute">Voir les données</summary>
                    <table class="table table-sm small mb-0 mt-2"><thead><tr><th>Semaine du</th><th class="text-end">Encaissé</th><th class="text-end">Paiements</th></tr></thead><tbody>
                        @foreach ($weekly as $w)<tr><td>{{ $w['week']->translatedFormat('j F') }}</td><td class="text-end">{{ $dh($w['value']) }}</td><td class="text-end">{{ $w['count'] }}</td></tr>@endforeach
                    </tbody></table>
                </details>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="box h-100">
            <div class="hd">Remplissage par jour <span>sur la période</span></div>
            <div class="p-3">
                @foreach ($occupancy['byWeekday'] as $d => $v)
                    @include('dashboard.partials.hbar', ['label' => $days[$d], 'value' => $v, 'max' => 1, 'text' => $pct($v), 'tip' => $days[$d].' · '.$pct($v).' du temps d’ouverture réservé'])
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-xl-6">
        <div class="box h-100">
            <div class="hd">D’où viennent les rendez-vous</div>
            <div class="p-3">
                @php $total = max(1, $sources->sum('value')); @endphp
                @foreach ($sources as $r)
                    @include('dashboard.partials.hbar', ['label' => $r['label'], 'value' => $r['value'], 'max' => $sources->max('value'), 'text' => $r['value'].' · '.round($r['value'] / $total * 100).' %', 'tip' => $r['label'].' : '.$r['value'].' rendez-vous'])
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="box h-100">
            <div class="hd">Soins les plus demandés</div>
            <div class="p-3">
                @foreach ($services as $r)
                    @include('dashboard.partials.hbar', ['label' => $r['label'], 'value' => $r['value'], 'max' => $services->max('value'), 'text' => (string) $r['value'], 'tip' => $r['label'].' : '.$r['value'].' rendez-vous'])
                @endforeach
            </div>
        </div>
    </div>
</div>
<div class="chart-tip" data-chart-tip hidden></div>
@endsection
