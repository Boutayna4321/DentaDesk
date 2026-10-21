@extends('layouts.dashboard')
@section('title', 'Patients')

@section('content')
<div class="top">
    <h1>Patients<small>{{ $patients->total() }} dossiers</small></h1>
    <div class="d-flex gap-2 flex-wrap">
        <form class="d-flex gap-2"><input class="form-control" name="q" value="{{ $q }}" placeholder="Nom ou téléphone…" style="width:240px" autofocus><button class="btn btn-outline-secondary">Chercher</button></form>
        <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#new-patient">+ Nouveau patient</button>
    </div>
</div>
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
        <thead><tr class="text-mute"><th class="ps-3">Patient</th><th>Téléphone</th><th>Couverture</th><th>Visites</th><th>Prochain RDV</th><th></th></tr></thead>
        <tbody>
        @foreach ($patients as $p)
            <tr>
                <td class="ps-3"><a class="fw-bold text-reset" href="{{ route('dash.patients.show', $p) }}">{{ $p->full_name }}</a>
                    @if ($p->allergies)<span class="tag">Allergie</span>@endif
                    <div class="text-mute">{{ $p->age ? $p->age.' ans' : '' }}</div></td>
                <td class="mono">{{ $p->phone }}</td>
                <td>{{ strtoupper($p->coverage ?? '—') }}</td>
                <td>{{ $p->visits_count }}</td>
                <td>{{ $p->appointments->first()?->starts_at->translatedFormat('D j M · H:i') ?? '—' }}</td>
                <td class="text-end pe-3"><a class="btn btn-outline-secondary btn-sm" href="https://wa.me/212{{ substr($p->phone, 1) }}" target="_blank" rel="noopener">WhatsApp</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
<div class="mt-3">{{ $patients->links() }}</div>
@endsection
