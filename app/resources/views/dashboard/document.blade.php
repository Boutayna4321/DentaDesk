@extends('layouts.dashboard')
@section('title', $doc->title)
@use('App\Billing\Money')
@php
    $states = ['draft' => ['Brouillon', 'st-pending'], 'to_collect' => ['À encaisser', 'st-arrived'], 'partial' => ['Payée en partie', 'st-arrived'],
        'paid' => ['Payée', 'st-done'], 'validated' => ['Validé', 'st-confirmed'], 'cancelled' => ['Annulé', 'st-cancelled']];
    [$stateLabel, $stateClass] = $states[$doc->payment_state];
@endphp

@section('content')
<div class="top">
    <h1>{{ $doc->title }} <span class="pill {{ $stateClass }} align-middle">{{ $stateLabel }}</span>
        <small><a class="text-reset" href="{{ route('dash.patients.show', $doc->patient) }}">{{ $doc->patient->full_name }}</a> · {{ $doc->issued_on?->translatedFormat('j F Y') }} · rédigé par {{ $doc->author?->name ?? '—' }}</small></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="{{ route('dash.documents.print', $doc) }}" target="_blank">Imprimer</a>
        @can('admin')
            @if ($doc->type === 'quote' && $doc->status === 'validated')
                <form method="post" action="{{ route('dash.documents.convert', $doc) }}">@csrf<button class="btn btn-outline-primary">Facturer ce devis</button></form>
            @endif
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="box p-0 overflow-hidden"><iframe src="{{ route('dash.documents.print', [$doc, 'embed' => 1]) }}" title="Aperçu" style="width:100%;height:860px;border:0;background:#e9edf4"></iframe></div>
    </div>
    <div class="col-xl-4">
        @if ($doc->isInvoice() && $doc->status === 'validated')
            <div class="box p-3">
                <div class="d-flex justify-content-between"><span class="text-mute">Total</span><b>{{ Money::format($doc->total_cents) }}</b></div>
                <div class="d-flex justify-content-between"><span class="text-mute">Réglé</span><b>{{ Money::format($doc->paid_cents) }}</b></div>
                <div class="d-flex justify-content-between fs-5 mt-1"><span>Reste</span><b class="{{ $doc->balance_cents ? 'text-danger' : 'text-success' }}">{{ Money::format($doc->balance_cents) }}</b></div>
                @if ($doc->balance_cents > 0)
                    <form class="mt-3 border-top pt-3" method="post" action="{{ route('dash.documents.pay', $doc) }}">
                        @csrf
                        <div class="fw-bold mb-2">Encaisser</div>
                        <div class="input-group input-group-sm mb-2"><input class="form-control text-end" name="amount" value="{{ Money::format($doc->balance_cents, false) }}" inputmode="decimal"><span class="input-group-text">DH</span></div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach ($methods as $k => $v)
                                <input type="radio" class="btn-check" name="method" id="m-{{ $k }}" value="{{ $k }}" @checked($loop->first)><label class="btn btn-outline-secondary btn-sm" for="m-{{ $k }}">{{ $v }}</label>
                            @endforeach
                        </div>
                        <input class="form-control form-control-sm mb-2" name="reference" placeholder="N° de chèque / reçu carte (facultatif)">
                        <button class="btn btn-primary btn-sm w-100">Enregistrer le paiement</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="box mt-3">
            <div class="hd">Paiements</div>
            @forelse ($doc->payments as $p)
                <div class="info-l px-3"><span>{{ $p->paid_on->format('d/m/Y') }} · {{ $methods[$p->method] }}{{ $p->reference ? ' · '.$p->reference : '' }} · {{ $p->receiver?->name }}</span><b>{{ Money::format($p->amount_cents) }}</b></div>
            @empty
                <p class="text-mute small p-3 mb-0">{{ $doc->isInvoice() ? 'Aucun paiement.' : 'Un devis ne s’encaisse pas : facturez-le une fois accepté.' }}</p>
            @endforelse
        </div>

        @if ($doc->status === 'cancelled')
            <div class="box mt-3 p-3 small"><b class="text-danger">Annulé le {{ $doc->cancelled_at->translatedFormat('j F Y') }}</b><div class="text-mute">{{ $doc->cancel_reason }}</div></div>
        @elseif ($doc->status === 'validated')
            @can('admin')
                <form class="box mt-3 p-3" method="post" action="{{ route('dash.documents.cancel', $doc) }}" data-confirm="Annuler {{ $doc->number }} ? Le numéro restera réservé.">
                    @csrf
                    <div class="fw-bold small mb-1">Annuler ce document</div>
                    <input class="form-control form-control-sm mb-2" name="reason" required placeholder="Motif (ex : erreur de montant)">
                    <button class="btn btn-outline-danger btn-sm">Annuler</button>
                </form>
            @endcan
        @endif
    </div>
</div>
@endsection
