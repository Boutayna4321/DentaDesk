@extends('layouts.dashboard')
@section('title', 'Aujourd’hui')

@section('content')
<div class="top">
    <h1>Bonjour {{ auth()->user()->isDentist() ? 'Dr '.\Illuminate\Support\Str::afterLast(auth()->user()->name, ' ') : strtok(auth()->user()->name, ' ') }}
        <small>{{ ucfirst($now->translatedFormat('l j F')) }} · {{ $kpi['today'] }} rendez-vous</small></h1>
    <div class="d-flex gap-2 flex-wrap">
        <form action="{{ route('dash.patients') }}"><input class="form-control" name="q" placeholder="Chercher un patient, un n° de tél…" style="width:260px"></form>
        <a class="btn btn-primary" href="{{ route('dash.agenda') }}">+ Nouveau RDV</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-6 col-xl-3"><div class="kpi"><span>Rendez-vous aujourd’hui</span><b>{{ $kpi['today'] }}</b><i>{{ $kpi['confirmed'] }} confirmés</i></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Créneaux encore libres</span><b>{{ $kpi['free']->count() }}</b><span class="mono">{{ $kpi['free']->take(4)->map->format('H:i')->implode(' · ') ?: '—' }}</span></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Réservés par l’assistant (7 j)</span><b>{{ $kpi['assistant'] }}</b>
        @php $d = $kpi['assistant'] - $kpi['assistantBefore']; @endphp<i class="{{ $d < 0 ? 'down' : '' }}">{{ $d >= 0 ? '+' : '' }}{{ $d }} vs semaine dernière</i></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><span>Absences ce mois</span><b>{{ $kpi['noShows'] }}</b><i class="{{ $kpi['noShows'] ? 'down' : '' }}">{{ $kpi['noShows'] ? 'à relancer' : 'aucune' }}</i></div></div>
</div>

<div class="row g-3 mt-0">
    <div class="col-xl-7">
        <div class="box">
            <div class="hd">Programme du jour <span>Fauteuil 1 · Dr Alaoui</span></div>
            @forelse ($appointments as $a)
                <div class="row-tl {{ $current?->id === $a->id ? 'now' : '' }}">
                    <span class="mono text-mute">{{ $a->starts_at->format('H:i') }}</span>
                    <div class="p"><b><a class="text-reset" href="{{ route('dash.patients.show', $a->patient) }}">{{ $a->patient->full_name }}</a>
                        @unless ($returning->has($a->patient_id))<span class="tag">1re visite</span>@endunless</b>
                        <span>{{ $a->service->name }}{{ $a->reason ? ' · '.$a->reason : '' }}</span></div>
                    <span class="src {{ $a->source }}">{{ $a->source_label }}</span>
                    @include('dashboard.partials.status', ['appointment' => $a])
                </div>
            @empty
                <p class="text-mute p-4 mb-0">Aucun rendez-vous aujourd’hui.</p>
            @endforelse
        </div>
    </div>
    <div class="col-xl-5">
        <div class="box">
            <div class="hd">Demandes à valider <span class="d-flex gap-2 align-items-center">@include('dashboard.partials.translate-toggle')<a href="{{ route('dash.requests') }}">{{ $openRequests }} en attente →</a></span></div>
            @forelse ($requests as $r)
                @include('dashboard.partials.request', ['r' => $r])
            @empty
                <p class="text-mute p-4 mb-0">Rien à valider. L’assistant a tout réglé.</p>
            @endforelse
        </div>
    </div>
</div>

@if ($current)
    @php $p = $current->patient; $last = $p->appointments->first(); @endphp
    <div class="row g-3 mt-0">
        <div class="col-xl-5">
            <div class="box h-100">
                <div class="hd">{{ $current->status === 'in_care' ? 'Patient en soin' : 'Prochain patient' }} <span>{{ $p->full_name }}{{ $p->age ? ' · '.$p->age.' ans' : '' }}</span></div>
                <div class="p-3">
                    <div class="info-l"><span>Téléphone</span><b class="mono">{{ $p->phone }}</b></div>
                    <div class="info-l"><span>Couverture</span><b>{{ strtoupper($p->coverage ?? '—') }}</b></div>
                    <div class="info-l"><span>Soin prévu</span><b>{{ $current->service->name }} · {{ $current->starts_at->format('H:i') }}</b></div>
                    <div class="info-l"><span>Dernière visite</span><b>{{ $last ? $last->starts_at->translatedFormat('j M').' · '.$last->service->name : 'Première visite' }}</b></div>
                    @if ($p->allergies)<div class="alert-allergy">⚠ Allergie : {{ $p->allergies }}</div>@endif
                    <a class="btn btn-outline-primary btn-sm mt-3" href="{{ route('dash.patients.show', $p) }}">Ouvrir le dossier</a>
                </div>
            </div>
        </div>
        @can('medical')
        <div class="col-xl-7">
            <div class="box h-100">
                <div class="hd">Schéma dentaire <span>{{ $p->first_name }}</span></div>
                <div class="p-3">
                    @include('dashboard.partials.odontogram', ['patient' => $p, 'teeth' => $p->teeth->keyBy('tooth'), 'editable' => true])
                    @if ($last?->visit_note)<div class="mt-3 small p-2 rounded" style="background:#f5f7fb"><b>Dernière note :</b> {{ $last->visit_note }}</div>@endif
                </div>
            </div>
        </div>
        @endcan
    </div>
@endif
@endsection
