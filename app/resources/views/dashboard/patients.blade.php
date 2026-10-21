@extends('layouts.dashboard')
@section('title', 'Patients')

@section('content')
<div class="top">
    <h1>Patients<small>{{ $patients->total() }} dossier{{ $patients->total() > 1 ? 's' : '' }}</small></h1>
    <div class="d-flex gap-2 flex-wrap">
        @php $nFilters = count($only) + count($coverage); @endphp
        <button class="btn btn-outline-secondary filter-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#patient-filters">Filtres @if ($nFilters)<span class="badge text-bg-primary">{{ $nFilters }}</span>@endif</button>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#new-patient">+ Nouveau patient</button>
    </div>
</div>

<form class="filters offcanvas-lg offcanvas-bottom" id="patient-filters" tabindex="-1" method="get">
    <div class="offcanvas-header d-lg-none"><b>Filtrer les patients</b><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#patient-filters" aria-label="Fermer"></button></div>
    <div class="f-grid">
        <label class="f-group"><span>Recherche</span><input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="Nom ou téléphone…" style="min-width:200px"></label>
        <div class="f-group"><span>Couverture</span><div class="f-chips">
            @foreach (\App\Http\Controllers\Dashboard\PatientController::COVERAGES as $k => $v)
                <input type="checkbox" name="couverture[]" value="{{ $k }}" id="cv-{{ $k }}" @checked(in_array($k, $coverage))><label for="cv-{{ $k }}">{{ $v }}</label>
            @endforeach
        </div></div>
        <div class="f-group"><span>Afficher seulement</span><div class="f-chips">
            @foreach (\App\Http\Controllers\Dashboard\PatientController::FILTERS as $k => $v)
                <input type="checkbox" name="only[]" value="{{ $k }}" id="f-{{ $k }}" @checked(in_array($k, $only))><label for="f-{{ $k }}">{{ $v }}</label>
            @endforeach
        </div></div>
        <label class="f-group"><span>Trier par</span><select class="form-select form-select-sm" name="tri">
            @foreach (\App\Http\Controllers\Dashboard\PatientController::SORTS as $k => $v)<option value="{{ $k }}" @selected($sort === $k)>{{ $v }}</option>@endforeach
        </select></label>
        <div class="f-actions"><a class="btn btn-link btn-sm" href="{{ route('dash.patients') }}">Réinitialiser</a><button class="btn btn-primary btn-sm">Appliquer</button></div>
    </div>
</form>

@php
    $chips = collect($only)->map(fn ($k) => [\App\Http\Controllers\Dashboard\PatientController::FILTERS[$k], request()->fullUrlWithQuery(['only' => array_values(array_diff($only, [$k])), 'page' => null])])
        ->concat(collect($coverage)->map(fn ($k) => [\App\Http\Controllers\Dashboard\PatientController::COVERAGES[$k], request()->fullUrlWithQuery(['couverture' => array_values(array_diff($coverage, [$k])), 'page' => null])]));
    if ($q !== '') $chips->push(['« '.$q.' »', request()->fullUrlWithQuery(['q' => null, 'page' => null])]);
@endphp
@if ($chips->isNotEmpty())
    <div class="active-filters">@foreach ($chips as [$label, $url])<a href="{{ $url }}">{{ $label }}<i>✕</i></a>@endforeach</div>
@endif
<form class="box p-3 mb-3 collapse" id="new-patient" method="post" action="{{ route('dash.patients.store') }}">
    @csrf
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small text-mute mb-0">Prénom</label><input class="form-control form-control-sm" name="first_name" required></div>
        <div class="col-md-2"><label class="form-label small text-mute mb-0">Nom</label><input class="form-control form-control-sm" name="last_name"></div>
        <div class="col-md-2"><label class="form-label small text-mute mb-0">Téléphone</label><input class="form-control form-control-sm" name="phone" required placeholder="06 12 34 56 78"></div>
        <div class="col-md-2"><label class="form-label small text-mute mb-0">Naissance</label><input class="form-control form-control-sm" type="date" name="birth_date"></div>
        <div class="col-md-2"><label class="form-label small text-mute mb-0">Couverture</label><select class="form-select form-select-sm" name="coverage"><option value="">—</option><option value="cnss">CNSS</option><option value="cnops">CNOPS</option><option value="mutuelle">Mutuelle</option><option value="aucune">Aucune</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Créer le dossier</button></div>
    </div>
</form>
<div class="box">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr class="text-mute"><th class="ps-3">Patient</th><th>Téléphone</th><th>Couverture</th><th>Visites</th><th>Dernière visite</th><th>Prochain RDV</th><th></th></tr></thead>
        <tbody>
        @forelse ($patients as $p)
            <tr>
                <td class="ps-3"><a class="fw-bold text-reset" href="{{ route('dash.patients.show', $p) }}">{{ $p->full_name }}</a>
                    @if ($p->allergies)<span class="tag">Allergie</span>@endif
                    <div class="text-mute">{{ $p->age ? $p->age.' ans' : '' }}</div></td>
                <td class="mono">{{ $p->phone }}</td>
                <td>{{ strtoupper($p->coverage ?? '—') }}</td>
                <td>{{ $p->visits_count }}</td>
                <td>{{ $p->last_visit ? \Carbon\Carbon::parse($p->last_visit)->translatedFormat('j M Y') : '—' }}</td>
                <td>{{ $p->appointments->first()?->starts_at->translatedFormat('D j M · H:i') ?? '—' }}</td>
                <td class="text-end pe-3"><a class="btn btn-outline-secondary btn-sm" href="https://wa.me/212{{ substr($p->phone, 1) }}" target="_blank" rel="noopener">WhatsApp</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-mute p-4">Aucun patient ne correspond à ces filtres.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-3">{{ $patients->links() }}</div>
@endsection
