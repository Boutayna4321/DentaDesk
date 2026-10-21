@extends('layouts.dashboard')
@section('title', 'Patients')

@section('content')
<div class="top">
    <h1>Patients<small>{{ $patients->total() }} dossiers</small></h1>
    <form class="d-flex gap-2"><input class="form-control" name="q" value="{{ $q }}" placeholder="Nom ou téléphone…" style="width:260px" autofocus><button class="btn btn-primary">Chercher</button></form>
</div>
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
