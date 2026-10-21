@use('App\Billing\Money')
@php
    $paper = $head['paper'] ?? 'A4';
    $accent = $head['accent'] ?? '#1f56d6';
    $isInvoice = $doc->type === 'invoice';
    $label = $isInvoice ? 'Facture' : 'Devis';
    $legal = collect(['inpe' => 'INPE', 'ice' => 'ICE', 'if' => 'IF', 'patente' => 'Patente', 'cnom' => 'Ordre'])
        ->filter(fn ($l, $k) => filled($head[$k] ?? null))->map(fn ($l, $k) => "$l : ".$head[$k]);
    $hasTeeth = $doc->lines->whereNotNull('tooth')->isNotEmpty();
    $hasCodes = $doc->lines->whereNotNull('code')->isNotEmpty();
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $doc->number ?? 'Brouillon' }} · {{ $doc->patient->full_name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style>
    @page { size: {{ $paper }}; margin: 0; }
    * { box-sizing: border-box; }
    html { background: #e9edf4; }
    body { margin: 0; font-family: Manrope, system-ui, sans-serif; color: #0c2350; font-size: {{ $paper === 'A5' ? '9.5pt' : '10.5pt' }}; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .embed .sheet { margin: 12px auto; }
    .sheet { width: {{ $paper === 'A5' ? '148mm' : '210mm' }}; min-height: {{ $paper === 'A5' ? '210mm' : '297mm' }}; margin: 24px auto; background: #fff;
        padding: {{ $paper === 'A5' ? '12mm 11mm' : '18mm 17mm' }}; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(12,35,80,.15); position: relative; }
    .bar { position: absolute; top: 0; left: 0; right: 0; height: 5px; background: {{ $accent }}; }
    header { display: flex; justify-content: space-between; gap: 10mm; padding-bottom: 6mm; border-bottom: 1px solid #e3e9f4; }
    header .who b { display: block; font-size: 1.45em; letter-spacing: -.02em; }
    header .who span { color: #5b6b86; }
    header .logo { max-height: 18mm; max-width: 45mm; object-fit: contain; }
    header .contact { text-align: right; color: #5b6b86; line-height: 1.5; font-size: .92em; }
    .title { display: flex; justify-content: space-between; align-items: flex-end; margin: 8mm 0 6mm; }
    .title h1 { margin: 0; font-size: 2em; letter-spacing: -.03em; font-weight: 800; }
    .title h1 small { display: block; font-family: 'IBM Plex Mono', monospace; font-size: .45em; color: {{ $accent }}; letter-spacing: .02em; font-weight: 500; margin-top: 1mm; }
    .title .date { text-align: right; color: #5b6b86; }
    .patient { background: #f5f7fb; border-radius: 2mm; padding: 4mm 5mm; display: flex; justify-content: space-between; gap: 6mm; }
    .patient b { font-size: 1.1em; }
    .patient .muted { color: #5b6b86; }
    table { width: 100%; border-collapse: collapse; margin-top: 7mm; }
    th { text-align: left; font-size: .72em; letter-spacing: .1em; text-transform: uppercase; color: #5b6b86; font-weight: 700; padding: 0 2mm 2mm; border-bottom: 1.5px solid #0c2350; }
    td { padding: 2.6mm 2mm; border-bottom: 1px solid #edf1f7; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mono { font-family: 'IBM Plex Mono', monospace; font-size: .9em; }
    .totals { margin-left: auto; width: {{ $paper === 'A5' ? '62mm' : '78mm' }}; margin-top: 4mm; }
    .totals div { display: flex; justify-content: space-between; padding: 1.6mm 2mm; }
    .totals .grand { background: {{ $accent }}; color: #fff; border-radius: 1.5mm; font-weight: 800; font-size: 1.15em; margin-top: 1mm; }
    .words { margin-top: 5mm; font-size: .95em; }
    .words em { font-style: normal; font-weight: 700; }
    .body { margin-top: 6mm; white-space: pre-line; line-height: 1.55; border-left: 2px solid {{ $accent }}; padding-left: 4mm; }
    .body b { display: block; font-size: .72em; letter-spacing: .1em; text-transform: uppercase; color: #5b6b86; margin-bottom: 1mm; }
    .paid { margin-top: 4mm; font-size: .9em; color: #1a8f5a; font-weight: 700; }
    .sign { margin-top: auto; padding-top: 10mm; display: flex; justify-content: space-between; align-items: flex-end; gap: 8mm; }
    .sign .note { color: #5b6b86; font-size: .88em; max-width: 60%; }
    .sign .box { width: {{ $paper === 'A5' ? '48mm' : '62mm' }}; height: 26mm; border: 1px dashed #b8c3d9; border-radius: 2mm; padding: 2mm 3mm; color: #98a3b8; font-size: .8em; }
    footer { margin-top: 7mm; padding-top: 3mm; border-top: 1px solid #e3e9f4; text-align: center; color: #5b6b86; font-size: .76em; line-height: 1.6; }
    .watermark { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; font-size: 26mm; font-weight: 800; color: rgba(192,57,43,.08); transform: rotate(-24deg); }
    /* Its own bar above the sheet: it can never sit on top of the invoice. */
    .toolbar { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; gap: 8px; padding: 10px 16px; background: #0c2350; color: #c9d5ee; font: 600 13px Manrope, sans-serif; }
    .toolbar[hidden] { display: none !important; }
    .toolbar .what { margin-right: auto; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .toolbar button, .toolbar a { font: 700 13px Manrope, sans-serif; border: 0; border-radius: 6px; padding: 9px 14px; background: #1f56d6; color: #fff; cursor: pointer; text-decoration: none; white-space: nowrap; }
    .toolbar a { background: rgba(255,255,255,.1); color: #fff; }
    @media print { html { background: #fff; } .sheet { margin: 0; box-shadow: none; } .toolbar { display: none; } }
</style>
</head>
<body class="{{ request()->boolean('embed') ? 'embed' : '' }}">
<div class="toolbar" @if (request()->boolean('embed')) hidden @endif>
    <a href="{{ url()->previous() }}">← Retour</a>
    <span class="what">{{ $doc->number ?? 'Brouillon' }} · {{ $doc->patient->full_name }} · {{ $paper }}</span>
    <button onclick="window.print()" title="Dans la fenêtre d’impression, choisissez « Enregistrer au format PDF » pour un fichier">Imprimer ou PDF</button>
</div>
<div class="sheet">
    <div class="bar"></div>
    @if ($doc->status === 'draft')<div class="watermark">BROUILLON</div>@endif
    @if ($doc->status === 'cancelled')<div class="watermark">ANNULÉ</div>@endif

    <header>
        <div class="who">
            @if (! empty($head['logo']))<img class="logo" src="{{ $head['logo'] }}" alt=""><br>@endif
            <b>{{ $head['doctor'] }}</b>
            <span>{{ $head['specialty'] }}</span>
        </div>
        <div class="contact">
            {{ $head['address'] }}<br>
            @if (filled($head['phone']))Tél. {{ $head['phone'] }}<br>@endif
            @if (filled($head['email'])){{ $head['email'] }}@endif
        </div>
    </header>

    <div class="title">
        <h1>{{ $label }}<small>N° {{ $doc->number ?? '— (brouillon)' }}</small></h1>
        <div class="date">{{ $head['city'] }}, le {{ ($doc->issued_on ?? now())->translatedFormat('j F Y') }}</div>
    </div>

    <div class="patient">
        <div><span class="muted">Patient</span><br><b>{{ $doc->patient->full_name }}</b></div>
        <div class="muted" style="text-align:right">
            @if ($doc->patient->coverage)Couverture : {{ strtoupper($doc->patient->coverage) }}<br>@endif
            @if ($doc->patient->birth_date)Né(e) le {{ $doc->patient->birth_date->format('d/m/Y') }}@endif
        </div>
    </div>

    <table>
        <thead><tr><th>Désignation</th>@if ($hasTeeth)<th>Dent</th>@endif @if ($hasCodes)<th>Cotation</th>@endif<th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Montant</th></tr></thead>
        <tbody>
        @foreach ($doc->lines as $l)
            <tr>
                <td>{{ $l->label }}</td>
                @if ($hasTeeth)<td class="mono">{{ $l->tooth }}</td>@endif
                @if ($hasCodes)<td class="mono">{{ $l->code }}</td>@endif
                <td class="num">{{ $l->quantity }}</td>
                <td class="num">{{ Money::format($l->unit_price_cents) }}</td>
                <td class="num">{{ Money::format($l->amount_cents) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="grand"><span>Total</span><span>{{ Money::format($doc->total_cents) }}</span></div>
        @if ($isInvoice && $doc->paid_cents > 0)
            <div><span>Déjà réglé</span><span>{{ Money::format($doc->paid_cents) }}</span></div>
            <div><span>Reste à payer</span><b>{{ Money::format($doc->balance_cents) }}</b></div>
        @endif
    </div>

    <div class="words">{{ $isInvoice ? 'Arrêtée la présente facture' : 'Arrêté le présent devis' }} à la somme de : <em>{{ ucfirst($words) }}</em>.</div>

    @if ($isInvoice && $doc->payments->isNotEmpty())
        <div class="paid">Réglé : @foreach ($doc->payments as $p){{ Money::format($p->amount_cents) }} par {{ mb_strtolower(\App\Models\Payment::METHODS[$p->method]) }} le {{ $p->paid_on->format('d/m/Y') }}{{ $loop->last ? '.' : ', ' }}@endforeach</div>
    @endif

    @if (filled($doc->body))
        <div class="body"><b>{{ $isInvoice ? 'Observations' : 'Plan de traitement' }}</b>{{ $doc->body }}</div>
    @endif

    <div class="sign">
        <div class="note">{{ $isInvoice ? $head['invoice_note'] : $head['quote_note'] }}</div>
        @if ($head['show_signature'] ?? true)<div class="box">Cachet et signature</div>@endif
    </div>

    <footer>
        @if ($legal->isNotEmpty()){{ $legal->implode(' · ') }}<br>@endif
        @if (filled($head['rib']))RIB : {{ $head['rib'] }}<br>@endif
        {{ $head['footer'] }}
    </footer>
</div>
</body>
</html>
