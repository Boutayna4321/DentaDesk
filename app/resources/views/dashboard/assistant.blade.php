@extends('layouts.dashboard')
@section('title', 'Assistant AI')

@php
    $labels = ['prendre_rdv' => 'Prendre RDV', 'urgence' => 'Urgence', 'annuler' => 'Annuler', 'deplacer' => 'Déplacer', 'horaires' => 'Horaires',
        'adresse' => 'Adresse', 'question_faq' => 'Question', 'salutation' => 'Salutation', 'merci' => 'Merci', 'affirmer' => 'Oui', 'nier' => 'Non', 'hors_sujet' => 'Hors sujet'];
    $confClass = fn ($c) => $c === null ? '' : ($c >= ($health['thresholds']['act'] ?? .58) ? 'hi' : ($c >= ($health['thresholds']['clarify'] ?? .26) ? 'mid' : 'lo'));
@endphp

@section('content')
<div class="top"><h1>Assistant AI<small>Modèle entraîné par nous, hébergé au cabinet. Aucune donnée patient ne sort vers une API externe.</small></h1></div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="box p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <b>Service NLU</b>
                @if ($health)<span class="pill st-done">En ligne</span>@else<span class="pill st-no_show">Hors ligne</span>@endif
            </div>
            @if ($health)
                <div class="info-l mt-2"><span>Version du modèle</span><b class="mono">{{ $health['model_version'] }}</b></div>
                <div class="info-l"><span>Exactitude (test écrit à la main)</span><b>{{ number_format($health['metrics']['test_accuracy'] * 100, 1, ',', ' ') }} %</b></div>
                <div class="info-l"><span>F1 macro</span><b>{{ number_format($health['metrics']['test_macro_f1'], 3, ',', ' ') }}</b></div>
                <div class="info-l"><span>Agit seul si confiance ≥</span><b class="mono">{{ $health['thresholds']['act'] }}</b></div>
                <div class="info-l"><span>Demande confirmation ≥</span><b class="mono">{{ $health['thresholds']['clarify'] }}</b></div>
                <div class="info-l"><span>Seuil FAQ</span><b class="mono">{{ $health['thresholds']['faq'] }}</b></div>
            @else
                <p class="small text-mute mt-2 mb-0">L’assistant répond aux patients avec le numéro du cabinet tant que le service est arrêté.</p>
            @endif
        </div>
    </div>
    <div class="col-xl-8">
        <div class="row g-3">
            <div class="col-4"><div class="kpi"><span>Conversations (30 j)</span><b>{{ $stats['conversations'] }}</b></div></div>
            <div class="col-4"><div class="kpi"><span>Réservations par l’assistant</span><b>{{ $stats['bookings'] }}</b></div></div>
            <div class="col-4"><div class="kpi"><span>Passées au cabinet</span><b>{{ $stats['handovers'] }}</b></div></div>
        </div>
        <div class="box mt-3">
            <div class="hd">Ce que demandent les patients <span>confiance moyenne {{ $stats['avgConfidence'] ? number_format($stats['avgConfidence'], 2, ',', '') : '—' }} · {{ $stats['corrections'] }} corrections pour le réentraînement</span></div>
            <div class="p-3">
                @php $max = max(1, $byIntent->max('n')); @endphp
                @forelse ($byIntent as $row)
                    <div class="d-flex align-items-center gap-2 small mb-2">
                        <span style="width:110px">{{ $labels[$row->intent] ?? $row->intent }}</span>
                        <div class="bar flex-grow-1"><span style="width: {{ round($row->n / $max * 100) }}%"></span></div>
                        <span class="mono" style="width:30px">{{ $row->n }}</span>
                        <span class="conf {{ $confClass($row->conf) }}">{{ number_format($row->conf, 2) }}</span>
                    </div>
                @empty
                    <p class="text-mute small mb-0">Pas encore de messages.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="box mt-3">
    <div class="hd">Dernières conversations <span>corrigez une intention mal comprise : elle rejoint les données d’entraînement</span></div>
    @foreach ($conversations as $c)
        <div class="conv">
            <div class="small mb-1"><b>{{ $c->patient?->full_name ?? 'Visiteur' }}</b> <span class="text-mute">· {{ $c->last_message_at?->diffForHumans() }}</span></div>
            @foreach ($c->messages as $m)
                <div class="line {{ $m->role }}">
                    <span class="role">{{ $m->role === 'patient' ? 'Patient' : 'Assistant' }}</span>
                    <span>{{ $m->body }}</span>
                    @if ($m->role === 'patient' && $m->intent)
                        <form class="intent" method="post" action="{{ route('dash.assistant.correct', $m) }}">
                            @csrf
                            <span class="conf {{ $confClass($m->confidence) }}">{{ number_format($m->confidence, 2) }}</span>
                            <select class="form-select form-select-sm py-0" name="corrected_intent" onchange="this.form.submit()" style="width:auto;font-size:.72rem">
                                @foreach ($intents as $i)<option value="{{ $i }}" @selected(($m->corrected_intent ?? $m->intent) === $i)>{{ $labels[$i] }}{{ $m->corrected_intent === $i ? ' ✎' : '' }}</option>@endforeach
                            </select>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
