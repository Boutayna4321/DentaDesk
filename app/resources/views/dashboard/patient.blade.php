@extends('layouts.dashboard')
@section('title', $patient->full_name)

@section('content')
<div class="top">
    <h1>{{ $patient->full_name }}<small>{{ $patient->age ? $patient->age.' ans · ' : '' }}<span class="mono">{{ $patient->phone }}</span> · patient depuis {{ $patient->created_at->translatedFormat('F Y') }}</small></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="https://wa.me/212{{ substr($patient->phone, 1) }}" target="_blank" rel="noopener">WhatsApp</a>
        <a class="btn btn-primary" href="{{ route('dash.agenda') }}">+ Rendez-vous</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <form class="box p-3" method="post" action="{{ route('dash.patients.update', $patient) }}">
            @csrf @method('put')
            <div class="fw-bold mb-2">Informations</div>
            <div class="row g-2">
                <div class="col-6"><label class="form-label small text-mute mb-0">Prénom</label><input class="form-control form-control-sm" name="first_name" value="{{ $patient->first_name }}"></div>
                <div class="col-6"><label class="form-label small text-mute mb-0">Nom</label><input class="form-control form-control-sm" name="last_name" value="{{ $patient->last_name }}"></div>
                <div class="col-6"><label class="form-label small text-mute mb-0">Téléphone</label><input class="form-control form-control-sm" name="phone" value="{{ $patient->phone }}"></div>
                <div class="col-6"><label class="form-label small text-mute mb-0">Naissance</label><input class="form-control form-control-sm" type="date" name="birth_date" value="{{ $patient->birth_date?->toDateString() }}"></div>
                <div class="col-12"><label class="form-label small text-mute mb-0">Couverture</label>
                    <select class="form-select form-select-sm" name="coverage"><option value="">—</option>@foreach (['cnss' => 'CNSS', 'cnops' => 'CNOPS', 'mutuelle' => 'Mutuelle privée', 'aucune' => 'Aucune'] as $k => $v)<option value="{{ $k }}" @selected($patient->coverage === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label small text-mute mb-0">Allergies</label><input class="form-control form-control-sm {{ $patient->allergies ? 'border-danger text-danger fw-bold' : '' }}" name="allergies" value="{{ $patient->allergies }}"></div>
                <div class="col-12"><label class="form-label small text-mute mb-0">Remarques (accueil)</label><textarea class="form-control form-control-sm" name="notes" rows="2">{{ $patient->notes }}</textarea></div>
            </div>
            <button class="btn btn-primary btn-sm mt-3">Enregistrer</button>
        </form>
    </div>

    <div class="col-xl-8">
        @can('medical')
            <div class="box mb-3">
                <div class="hd">Schéma dentaire <span>numérotation FDI</span></div>
                <div class="p-3">@include('dashboard.partials.odontogram', ['patient' => $patient, 'teeth' => $teeth, 'editable' => true])</div>
            </div>
        @endcan
        <div class="box">
            <div class="hd">Rendez-vous <span>{{ $patient->appointments->count() }}</span></div>
            @foreach ($patient->appointments as $a)
                <div class="row-tl" style="grid-template-columns: 120px 1fr 110px 130px">
                    <span class="mono text-mute">{{ $a->starts_at->format('d/m/y H:i') }}</span>
                    <div class="p"><b>{{ $a->service->name }}</b><span>{{ $a->reason }}</span>
                        @can('medical')
                            @if ($a->status === 'done' || $a->status === 'in_care')
                                <form class="mt-1 d-flex gap-1" method="post" action="{{ route('dash.appointments.note', $a) }}">@csrf @method('patch')
                                    <input class="form-control form-control-sm" name="visit_note" value="{{ $a->visit_note }}" placeholder="Note de séance…"><button class="btn btn-outline-secondary btn-sm">OK</button></form>
                            @endif
                        @endcan
                    </div>
                    <span class="src {{ $a->source }}">{{ $a->source_label }}</span>
                    @include('dashboard.partials.status', ['appointment' => $a])
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
