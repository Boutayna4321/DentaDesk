@extends('layouts.dashboard')
@section('title', 'Facturation')
@use('App\Billing\Money')

@section('content')
<div class="top">
    <h1>Facturation<small>En-tête et pied de page de vos factures et devis, et vos tarifs</small></h1>
    <a class="btn btn-outline-secondary" href="{{ route('dash.billing.preview') }}" target="_blank">Aperçu d’une facture</a>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <form class="box" method="post" action="{{ route('dash.billing.letterhead') }}" enctype="multipart/form-data">
            @csrf @method('put')
            <div class="hd">En-tête &amp; pied de page <span>les champs vides ne s’impriment pas</span></div>
            <div class="p-3">
                <div class="row g-2">
                    @foreach ($fields as $key => $label)
                        <div class="{{ in_array($key, ['footer', 'invoice_note', 'quote_note', 'address']) ? 'col-12' : 'col-md-6' }}">
                            <label class="form-label small text-mute mb-0">{{ $label }}</label>
                            @if (in_array($key, ['footer', 'invoice_note', 'quote_note']))
                                <textarea class="form-control form-control-sm" name="{{ $key }}" rows="2">{{ old($key, $head[$key]) }}</textarea>
                            @else
                                <input class="form-control form-control-sm" name="{{ $key }}" value="{{ old($key, $head[$key]) }}">
                            @endif
                        </div>
                    @endforeach
                    <div class="col-md-4"><label class="form-label small text-mute mb-0">Format</label>
                        <select class="form-select form-select-sm" name="paper">@foreach (['A4', 'A5'] as $p)<option @selected($head['paper'] === $p)>{{ $p }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label small text-mute mb-0">Couleur</label><input class="form-control form-control-sm form-control-color w-100" type="color" name="accent" value="{{ $head['accent'] }}"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="hidden" name="show_signature" value="0"><input class="form-check-input" type="checkbox" name="show_signature" value="1" id="sig" @checked($head['show_signature'])><label class="form-check-label small" for="sig">Cadre cachet &amp; signature</label></div></div>
                    <div class="col-12"><label class="form-label small text-mute mb-0">Logo (PNG/JPG, 1 Mo max)</label>
                        <div class="d-flex gap-2 align-items-center">
                            @if ($head['logo'])<img src="{{ $head['logo'] }}" alt="" style="height:36px"><label class="small"><input type="checkbox" name="remove_logo" value="1"> retirer</label>@endif
                            <input class="form-control form-control-sm" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                        </div></div>
                </div>
                <button class="btn btn-primary btn-sm mt-3">Enregistrer</button>
                <p class="small text-mute mt-2 mb-0">Les factures déjà validées gardent l’en-tête du jour de leur validation.</p>
            </div>
        </form>
    </div>

    <div class="col-xl-6">
        <form class="box" method="post" action="{{ route('dash.billing.acts') }}">
            @csrf @method('put')
            <div class="hd">Vos tarifs <span>proposés dans l’éditeur · jamais affichés sur le site</span></div>
            <table class="table align-middle small mb-0">
                <thead><tr class="text-mute"><th class="ps-3">Acte</th><th style="width:90px">Cotation</th><th style="width:120px">Prix</th><th class="text-center" style="width:60px">Actif</th></tr></thead>
                <tbody>
                @foreach ($acts as $a)
                    <tr>
                        <td class="ps-3"><input class="form-control form-control-sm" name="acts[{{ $a->id }}][label]" value="{{ $a->label }}"></td>
                        <td><input class="form-control form-control-sm mono" name="acts[{{ $a->id }}][code]" value="{{ $a->code }}"></td>
                        <td><div class="input-group input-group-sm"><input class="form-control text-end" name="acts[{{ $a->id }}][price]" value="{{ Money::format($a->price_cents, false) }}"><span class="input-group-text">DH</span></div></td>
                        <td class="text-center"><input type="hidden" name="acts[{{ $a->id }}][is_active]" value="0"><input class="form-check-input" type="checkbox" name="acts[{{ $a->id }}][is_active]" value="1" @checked($a->is_active)></td>
                    </tr>
                @endforeach
                <tr class="table-light">
                    <td class="ps-3"><input class="form-control form-control-sm" name="acts[new][label]" placeholder="+ Nouvel acte"></td>
                    <td><input class="form-control form-control-sm mono" name="acts[new][code]"></td>
                    <td><div class="input-group input-group-sm"><input class="form-control text-end" name="acts[new][price]"><span class="input-group-text">DH</span></div></td>
                    <td></td>
                </tr>
                </tbody>
            </table>
            <div class="p-3"><button class="btn btn-primary btn-sm">Enregistrer les tarifs</button></div>
        </form>
    </div>
</div>
@endsection
