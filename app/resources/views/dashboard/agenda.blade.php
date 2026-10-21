@extends('layouts.dashboard')
@section('title', 'Agenda')

@section('content')
<div class="top">
    <h1>Agenda<small>Glissez un rendez-vous pour le déplacer · sélectionnez une plage vide pour en créer un</small></h1>
    <div class="d-flex gap-3 small text-mute align-items-center flex-wrap">
        <span><span class="pill st-pending">À confirmer</span></span><span><span class="pill st-confirmed">Confirmé</span></span>
        <span><span class="pill st-in_care">En soin</span></span><span><span class="pill st-done">Terminé</span></span>
    </div>
</div>
<div class="box p-3">
    <div data-calendar data-events="{{ route('dash.agenda.events') }}" data-move="{{ url('dashboard/appointments/:id/move') }}" data-business='@json($businessHours)'></div>
</div>

{{-- New appointment --}}
<div class="modal fade" id="new-rdv" tabindex="-1">
    <div class="modal-dialog"><form class="modal-content" method="post" action="{{ route('dash.appointments.store') }}" data-new-rdv>
        @csrf
        <div class="modal-header"><h2 class="modal-title h6">Nouveau rendez-vous <span class="text-mute fw-semibold" data-when></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="starts_at">
            <input type="hidden" name="patient_id">
            <label class="form-label small fw-bold">Patient</label>
            <input class="form-control" data-patient-search placeholder="Nom ou téléphone…" autocomplete="off">
            <div class="list-group mt-1" data-patient-results></div>
            <div class="row g-2 mt-1" data-new-patient>
                <div class="col-6"><input class="form-control form-control-sm" name="first_name" placeholder="Prénom (nouveau patient)"></div>
                <div class="col-6"><input class="form-control form-control-sm" name="phone" placeholder="06 12 34 56 78"></div>
            </div>
            <label class="form-label small fw-bold mt-3">Soin</label>
            <select class="form-select" name="service">
                @foreach ($services as $s)<option value="{{ $s->slug }}">{{ $s->name }} · {{ $s->duration_minutes }} min</option>@endforeach
            </select>
            <label class="form-label small fw-bold mt-3">Motif</label>
            <input class="form-control" name="reason" placeholder="Optionnel">
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Enregistrer</button></div>
    </form></div>
</div>

{{-- Appointment details --}}
<div class="modal fade" id="rdv" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h6" data-title></h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body small">
            <div class="info-l"><span>Soin</span><b data-service></b></div>
            <div class="info-l"><span>Heure</span><b data-time></b></div>
            <div class="info-l"><span>Téléphone</span><b class="mono" data-phone></b></div>
            <div class="info-l"><span>Réservé via</span><b data-source></b></div>
            <div class="info-l"><span>Motif</span><b data-reason></b></div>
            <label class="form-label small fw-bold mt-3">Statut</label>
            <div class="d-flex flex-wrap gap-1" data-statuses>
                @foreach (\App\Models\Appointment::STATUSES as $key => $label)
                    <button class="pill st-{{ $key }}" data-status="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="modal-footer"><a class="btn btn-outline-primary btn-sm" data-patient>Dossier patient</a></div>
    </div></div>
</div>
@endsection
