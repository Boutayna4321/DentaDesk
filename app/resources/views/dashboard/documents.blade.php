@extends('layouts.dashboard')
@section('title', 'Factures')
@use('App\Billing\Money')
@php
    $states = ['draft' => ['Brouillon', 'st-pending'], 'to_collect' => ['À encaisser', 'st-arrived'], 'partial' => ['Payée en partie', 'st-arrived'],
        'paid' => ['Payée', 'st-done'], 'validated' => ['Validé', 'st-confirmed'], 'cancelled' => ['Annulé', 'st-cancelled']];
@endphp

@section('content')
<div class="top">
    <h1>Factures &amp; devis<small>Encaissé aujourd’hui : <b class="text-success">{{ Money::format($today) }}</b></small></h1>
    @can('admin')<a class="btn btn-outline-secondary" href="{{ route('dash.billing') }}">En-tête, pied de page &amp; tarifs</a>@endcan
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <ul class="nav nav-pills small gap-1 mb-0">
        @foreach (['collect' => 'À encaisser', 'drafts' => 'Brouillons', 'quotes' => 'Devis', 'all' => 'Tout'] as $k => $v)
            <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="?tab={{ $k }}">{{ $v }}@if (isset($counts[$k]) && $counts[$k]) <span class="badge text-bg-light ms-1">{{ $counts[$k] }}</span>@endif</a></li>
        @endforeach
    </ul>
    @php $nFilters = count($f['states']) + count($f['methods']) + ($f['from'] ? 1 : 0) + ($f['to'] ? 1 : 0) + ($f['q'] !== '' ? 1 : 0); @endphp
    <button class="btn btn-outline-secondary btn-sm filter-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#doc-filters">Filtres @if ($nFilters)<span class="badge text-bg-primary">{{ $nFilters }}</span>@endif</button>
</div>

<form class="filters offcanvas-lg offcanvas-bottom" id="doc-filters" tabindex="-1" method="get">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="offcanvas-header d-lg-none"><b>Filtrer les factures</b><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#doc-filters" aria-label="Fermer"></button></div>
    <div class="f-grid">
        <label class="f-group"><span>Patient ou n°</span><input class="form-control form-control-sm" name="q" value="{{ $f['q'] }}" placeholder="Salma, F-2026-0042…"></label>
        <label class="f-group"><span>Du</span><input class="form-control form-control-sm" type="date" name="du" value="{{ $f['from'] }}"></label>
        <label class="f-group"><span>Au</span><input class="form-control form-control-sm" type="date" name="au" value="{{ $f['to'] }}"></label>
        <div class="f-group"><span>État</span><div class="f-chips">
            @foreach (\App\Http\Controllers\Dashboard\DocumentController::STATES as $k => $v)
                <input type="checkbox" name="etat[]" value="{{ $k }}" id="st-{{ $k }}" @checked(in_array($k, $f['states']))><label for="st-{{ $k }}">{{ $v }}</label>
            @endforeach
        </div></div>
        <div class="f-group"><span>Payé par</span><div class="f-chips">
            @foreach (\App\Models\Payment::METHODS as $k => $v)
                <input type="checkbox" name="paiement[]" value="{{ $k }}" id="pm-{{ $k }}" @checked(in_array($k, $f['methods']))><label for="pm-{{ $k }}">{{ $v }}</label>
            @endforeach
        </div></div>
        <div class="f-actions"><a class="btn btn-link btn-sm" href="?tab={{ $tab }}">Réinitialiser</a><button class="btn btn-primary btn-sm">Appliquer</button></div>
    </div>
</form>

@if ($nFilters)
    <p class="small text-mute mb-2">{{ $sum->n }} document(s) · total {{ Money::format((int) $sum->total) }} · encaissé {{ Money::format((int) $sum->paid) }}</p>
@endif

<div class="box">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr class="text-mute"><th class="ps-3">N°</th><th>Patient</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Reste</th><th>État</th><th class="pe-3"></th></tr></thead>
        <tbody>
        @forelse ($documents as $d)
            @php [$l, $c] = $states[$d->payment_state]; @endphp
            <tr>
                <td class="ps-3 mono">{{ $d->number ?? '—' }}<div class="text-mute" style="font-family:Manrope">{{ \App\Models\Document::TYPES[$d->type] }}</div></td>
                <td><a class="fw-bold text-reset" href="{{ route('dash.patients.show', $d->patient) }}">{{ $d->patient->full_name }}</a></td>
                <td>{{ ($d->issued_on ?? $d->updated_at)->format('d/m/Y') }}</td>
                <td class="text-end">{{ Money::format($d->total_cents) }}</td>
                <td class="text-end {{ $d->isInvoice() && $d->balance_cents && $d->status === 'validated' ? 'text-danger fw-bold' : 'text-mute' }}">{{ $d->isInvoice() && $d->status === 'validated' ? Money::format($d->balance_cents) : '—' }}</td>
                <td><span class="pill {{ $c }}">{{ $l }}</span></td>
                <td class="text-end pe-3"><a class="btn btn-outline-secondary btn-sm" href="{{ $d->isDraft() ? route('dash.documents.edit', $d) : route('dash.documents.show', $d) }}">{{ $d->isDraft() ? 'Continuer' : 'Ouvrir' }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-mute p-4">Rien ici.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-3">{{ $documents->links() }}</div>
@endsection
