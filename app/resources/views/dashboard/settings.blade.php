@extends('layouts.dashboard')
@section('title', 'Horaires & soins')

@php $days = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']; @endphp

@section('content')
<div class="top"><h1>Horaires &amp; soins<small>Le site, la barre de réservation et l’assistant lisent ces réglages directement</small></h1></div>
<div class="row g-3">
    <div class="col-xl-6">
        <form class="box" method="post" action="{{ route('dash.settings.hours') }}">
            @csrf @method('put')
            <div class="hd">Horaires d’ouverture <span>laisser vide = fermé</span></div>
            <div class="p-3">
                @foreach ($days as $n => $name)
                    @php $spans = ($hours[$n] ?? collect())->values(); @endphp
                    <div class="d-flex align-items-center gap-2 mb-2 small">
                        <b style="width:80px">{{ $name }}</b>
                        @for ($i = 0; $i < 2; $i++)
                            <input class="form-control form-control-sm mono" type="time" step="900" name="hours[{{ $n }}][{{ $i }}][opens_at]" value="{{ isset($spans[$i]) ? substr($spans[$i]->opens_at, 0, 5) : '' }}">
                            <span class="text-mute">–</span>
                            <input class="form-control form-control-sm mono" type="time" step="900" name="hours[{{ $n }}][{{ $i }}][closes_at]" value="{{ isset($spans[$i]) ? substr($spans[$i]->closes_at, 0, 5) : '' }}">
                            @if ($i === 0)<span class="text-mute px-1">puis</span>@endif
                        @endfor
                    </div>
                @endforeach
                <button class="btn btn-primary btn-sm mt-2">Enregistrer les horaires</button>
            </div>
        </form>

        <div class="box mt-3">
            <div class="hd">Fermetures &amp; congés</div>
            <div class="p-3">
                @forelse ($timeOffs as $t)
                    <div class="info-l"><span>{{ $t->reason ?? 'Fermé' }}</span><b>{{ $t->starts_at->translatedFormat('D j M H:i') }} → {{ $t->ends_at->translatedFormat('D j M H:i') }}</b>
                        <form method="post" action="{{ route('dash.settings.timeoff.remove', $t) }}">@csrf @method('delete')<button class="btn btn-link btn-sm text-danger p-0">Retirer</button></form></div>
                @empty
                    <p class="small text-mute">Aucune fermeture prévue.</p>
                @endforelse
                <form class="row g-2 mt-2" method="post" action="{{ route('dash.settings.timeoff') }}">
                    @csrf
                    <div class="col-md-4"><input class="form-control form-control-sm" type="datetime-local" name="starts_at" required></div>
                    <div class="col-md-4"><input class="form-control form-control-sm" type="datetime-local" name="ends_at" required></div>
                    <div class="col-md-4"><input class="form-control form-control-sm" name="reason" placeholder="Congrès, Aïd…"></div>
                    <div class="col-12"><button class="btn btn-outline-primary btn-sm">Ajouter une fermeture</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <form class="box" method="post" action="{{ route('dash.settings.services') }}">
            @csrf @method('put')
            <div class="hd">Soins &amp; durées <span>la durée découpe les créneaux</span></div>
            <table class="table align-middle small mb-0">
                <thead><tr class="text-mute"><th class="ps-3">Soin</th><th>Durée</th><th class="text-center">En ligne</th></tr></thead>
                <tbody>
                @foreach ($services as $s)
                    <tr>
                        <td class="ps-3"><b>{{ $s->name }}</b>@if ($s->is_emergency)<span class="tag">Urgence</span>@endif<div class="text-mute">{{ $s->summary }}</div></td>
                        <td style="width:120px"><div class="input-group input-group-sm"><input class="form-control mono" type="number" min="15" max="240" step="15" name="services[{{ $s->id }}][duration_minutes]" value="{{ $s->duration_minutes }}"><span class="input-group-text">min</span></div></td>
                        <td class="text-center"><input type="hidden" name="services[{{ $s->id }}][bookable_online]" value="0"><input class="form-check-input" type="checkbox" name="services[{{ $s->id }}][bookable_online]" value="1" @checked($s->bookable_online)></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="p-3"><button class="btn btn-primary btn-sm">Enregistrer les soins</button></div>
        </form>
    </div>
</div>
@endsection
