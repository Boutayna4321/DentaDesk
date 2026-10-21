@extends('layouts.dashboard')
@section('title', 'Équipe')

@section('content')
<div class="top">
    <h1>Équipe<small>Qui peut se connecter, avec quels droits</small></h1>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#new-user">+ Nouveau compte</button>
</div>

<form class="box p-3 mb-3 collapse {{ $errors->any() && ! old('_user') ? 'show' : '' }}" id="new-user" method="post" action="{{ route('dash.users.store') }}">
    @csrf
    <div class="fw-bold mb-2">Nouveau compte</div>
    @include('dashboard.partials.user-fields', ['u' => null])
    <button class="btn btn-primary btn-sm mt-3">Créer le compte</button>
</form>

<div class="box">
    <div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr class="text-mute"><th class="ps-3">Personne</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th class="text-end pe-3">Actions</th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                <td class="ps-3"><div class="d-flex align-items-center gap-2"><span class="avatar">{{ $u->initials }}</span>
                    <div><b>{{ $u->name }}</b>@if ($u->is(auth()->user()))<span class="text-mute"> · vous</span>@endif<div class="text-mute">{{ $u->email }}{{ $u->phone ? ' · '.$u->phone : '' }}</div></div></div></td>
                <td>{{ $u->role_label }}<div class="text-mute">{{ $u->isDentist() ? 'tout, y compris dossiers médicaux et factures' : 'agenda, patients, demandes, encaissement' }}</div></td>
                <td>{{ $u->last_login_at?->diffForHumans() ?? 'jamais' }}</td>
                <td><span class="pill {{ $u->is_active ? 'st-done' : 'st-cancelled' }}">{{ $u->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                <td class="text-end pe-3">
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#edit-{{ $u->id }}">Modifier</button>
                        @unless ($u->is(auth()->user()))
                            <form method="post" action="{{ route('dash.users.toggle', $u) }}">@csrf @method('patch')<button class="btn btn-outline-secondary btn-sm">{{ $u->is_active ? 'Désactiver' : 'Réactiver' }}</button></form>
                            @if ($u->deletable)
                                <form method="post" action="{{ route('dash.users.destroy', $u) }}" data-confirm="Supprimer définitivement le compte de {{ $u->name }} ?">@csrf @method('delete')<button class="btn btn-outline-danger btn-sm">Supprimer</button></form>
                            @else
                                <button class="btn btn-outline-secondary btn-sm" disabled title="Ce compte a un historique : désactivez-le plutôt">Supprimer</button>
                            @endif
                        @endunless
                    </div>
                </td>
            </tr>
            <tr class="collapse" id="edit-{{ $u->id }}"><td colspan="5" class="bg-light">
                <form class="p-2" method="post" action="{{ route('dash.users.update', $u) }}">
                    @csrf @method('put')
                    @include('dashboard.partials.user-fields', ['u' => $u])
                    <button class="btn btn-primary btn-sm mt-3">Enregistrer</button>
                </form>
            </td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="box mt-3">
    <div class="hd">Journal <span>les 25 dernières actions sensibles</span></div>
    @forelse ($log as $l)
        <div class="info-l px-3"><span>{{ $l->created_at->translatedFormat('j M H:i') }} · {{ $l->user?->name ?? 'système' }}</span><b class="fw-semibold">{{ $l->description }}</b></div>
    @empty
        <p class="text-mute small p-3 mb-0">Rien pour l’instant.</p>
    @endforelse
</div>
@endsection
