@extends('layouts.dashboard')
@section('title', $doc->title)
@use('App\Billing\Money')

@section('content')
<form method="post" action="{{ route('dash.documents.update', $doc) }}" data-doc-editor>
    @csrf @method('put')
    <div class="top">
        <h1>{{ \App\Models\Document::TYPES[$doc->type] }} · brouillon<small>{{ $doc->patient->full_name }} · un brouillon se modifie librement ; une fois validé, il est numéroté et verrouillé</small></h1>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary" name="action" value="preview" formtarget="_blank">Aperçu</button>
            <button class="btn btn-outline-primary" name="action" value="save">Enregistrer le brouillon</button>
            <button class="btn btn-primary" name="action" value="validate" data-validate>Valider et numéroter</button>
        </div>
    </div>

    <div class="box">
        <div class="hd">Actes <span>choisissez dans vos tarifs ou écrivez librement</span></div>
        <div class="table-responsive"><table class="table align-middle small mb-0 lines">
            <thead><tr class="text-mute"><th class="ps-3" style="min-width:260px">Désignation</th><th style="width:80px">Dent</th><th style="width:100px">Cotation</th><th style="width:70px">Qté</th><th style="width:130px">Prix unitaire</th><th class="text-end" style="width:130px">Montant</th><th style="width:40px"></th></tr></thead>
            <tbody data-lines>
            @foreach (old('lines', $doc->lines->map(fn ($l) => ['label' => $l->label, 'tooth' => $l->tooth, 'code' => $l->code, 'quantity' => $l->quantity, 'unit_price' => Money::format($l->unit_price_cents, false)])->all()) as $i => $l)
                @include('dashboard.partials.document-line', ['i' => $i, 'l' => $l])
            @endforeach
            </tbody>
        </table></div>
        <div class="p-3 d-flex gap-2 flex-wrap align-items-center border-top">
            <select class="form-select form-select-sm" style="max-width:340px" data-act-picker>
                <option value="">+ Ajouter un acte de vos tarifs…</option>
                @foreach ($acts as $a)<option value="{{ $a->id }}" data-label="{{ $a->label }}" data-code="{{ $a->code }}" data-price="{{ Money::format($a->price_cents, false) }}">{{ $a->label }} · {{ Money::format($a->price_cents) }}</option>@endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-add-line>+ Ligne libre</button>
            <div class="ms-auto fs-5 fw-bold">Total <span data-total>{{ Money::format($doc->total_cents) }}</span></div>
        </div>
    </div>

    <div class="box mt-3">
        <div class="hd">{{ $doc->type === 'quote' ? 'Plan de traitement' : 'Observations' }} <span>imprimé sous le tableau · facultatif</span></div>
        <div class="p-3"><textarea class="form-control" name="body" rows="5" placeholder="{{ $doc->type === 'quote' ? 'Ex : Séance 1 — extraction 36. Séance 2 (3 mois après) — pose de l’implant…' : 'Ex : Soins réalisés le 21/10. Contrôle dans 6 mois.' }}">{{ old('body', $doc->body) }}</textarea></div>
    </div>
</form>

<template data-line-template>@include('dashboard.partials.document-line', ['i' => '__i__', 'l' => ['label' => '', 'tooth' => null, 'code' => null, 'quantity' => 1, 'unit_price' => '']])</template>

<form class="mt-3" method="post" action="{{ route('dash.documents.destroy', $doc) }}" data-confirm="Supprimer ce brouillon ?">@csrf @method('delete')<button class="btn btn-link text-danger btn-sm p-0">Supprimer le brouillon</button></form>
@endsection
