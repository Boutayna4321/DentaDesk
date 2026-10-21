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

<ul class="nav nav-pills small mb-3 gap-1">
    @foreach (['collect' => 'À encaisser', 'drafts' => 'Brouillons', 'quotes' => 'Devis', 'all' => 'Tout'] as $k => $v)
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="?tab={{ $k }}">{{ $v }}@if (isset($counts[$k]) && $counts[$k]) <span class="badge text-bg-light ms-1">{{ $counts[$k] }}</span>@endif</a></li>
    @endforeach
</ul>

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
