<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title') · DentaDesk cabinet</title>
    {{ Vite::fonts() }}
    @vite(['resources/scss/dashboard.scss', 'resources/js/dashboard.js'])
</head>
<body>
@php
    $open = \App\Models\AssistantRequest::where('status', 'open')->count();
    $nav = [
        ['dash.today', 'Aujourd’hui', 'clock', null],
        ['dash.agenda', 'Agenda', 'calendar', null],
        ['dash.requests', 'Demandes', 'chat', $open],
        ['dash.patients', 'Patients', 'child', null],
        ['dash.documents', 'Factures', 'card', \App\Models\Document::where('type', 'invoice')->where('status', 'validated')->whereColumn('paid_cents', '<', 'total_cents')->count()],
        ['dash.assistant', 'Assistant AI', 'sparkle', null],
    ];
    if (auth()->user()->can('admin')) {
        array_splice($nav, 5, 0, [['dash.stats', 'Statistiques', 'scan', null]]);
    }
@endphp
<header class="mtop">
    <button class="burger" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidenav" aria-controls="sidenav" aria-label="Ouvrir le menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <em data-burger-dot @if (! ($open || ($unreadTop = \App\Models\InternalMessage::where('recipient_id', auth()->id())->whereNull('read_at')->count()))) hidden @endif></em>
    </button>
    <b>DentaDesk</b>
    <span class="where">@yield('title')</span>
    <button class="burger search-btn" type="button" data-open-search aria-label="Rechercher">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    </button>
</header>
<div class="shell">
    <aside class="side offcanvas-lg offcanvas-start" id="sidenav" tabindex="-1" aria-label="Menu">
        <div class="brand"><span class="d-flex align-items-center gap-2"><x-icon name="tooth" size="22" stroke="#fff" />DentaDesk</span>
            <button class="side-close" type="button" data-bs-dismiss="offcanvas" data-bs-target="#sidenav" aria-label="Fermer le menu">✕</button></div>
        <button class="side-search" type="button" data-open-search>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            Rechercher <kbd>Ctrl K</kbd>
        </button>
        @foreach ($nav as [$route, $label, $icon, $badge])
            <a class="item {{ request()->routeIs($route.'*') ? 'on' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon" size="17" />{{ $label }}@if ($badge !== null)<em data-badge="{{ $route }}" @if (! $badge) hidden @endif>{{ $badge }}</em>@endif</a>
        @endforeach
        @php $unread = \App\Models\InternalMessage::where('recipient_id', auth()->id())->whereNull('read_at')->count(); @endphp
        <a class="item" href="#" data-open-messages><x-icon name="chat" size="17" />Messages<em data-badge="dash.messages" @if (! $unread) hidden @endif>{{ $unread }}</em></a>
        <div class="sep">CABINET</div>
        <a class="item {{ request()->routeIs('dash.settings') ? 'on' : '' }}" href="{{ route('dash.settings') }}"><x-icon name="doc" size="17" />Horaires &amp; soins</a>
        @can('admin')
            <a class="item {{ request()->routeIs('dash.billing*') ? 'on' : '' }}" href="{{ route('dash.billing') }}"><x-icon name="card" size="17" />Facturation</a>
            <a class="item {{ request()->routeIs('dash.users*') ? 'on' : '' }}" href="{{ route('dash.users') }}"><x-icon name="child" size="17" />Équipe</a>
        @endcan
        <a class="item" href="{{ route('home') }}" target="_blank"><x-icon name="pin" size="17" />Voir le site</a>
        <div class="me">
            <span class="avatar">{{ auth()->user()->initials }}</span>
            <div><b>{{ auth()->user()->name }}</b>{{ auth()->user()->role_label }}</div>
            <form method="post" action="{{ route('logout') }}">@csrf<button>Sortir</button></form>
        </div>
    </aside>
    <main class="main">
        <div class="toasts" data-toasts data-pulse="{{ route('dash.pulse') }}">
            @if (session('ok'))<div class="toast-msg ok" role="status">{{ session('ok') }}</div>@endif
            @if (session('error'))<div class="toast-msg err" role="alert">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="toast-msg err" role="alert">{{ $errors->first() }}</div>@endif
        </div>
        @yield('content')
    </main>
</div>
<div class="search-layer" data-search-layer data-url="{{ route('dash.search') }}" hidden>
    <div class="search-box" role="dialog" aria-label="Recherche">
        <div class="search-input">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" placeholder="Patient, téléphone, n° de facture…" autocomplete="off" data-search-input aria-label="Rechercher">
            <button type="button" data-close-search aria-label="Fermer">Annuler</button>
        </div>
        <div class="search-results" data-search-results><p class="hint">Tapez au moins 2 lettres ou chiffres.</p></div>
    </div>
</div>
<aside class="msg-panel" data-messages="{{ route('dash.messages') }}" data-send="{{ route('dash.messages.store') }}" hidden aria-label="Messages internes">
    <div class="mp-hd">
        <b>Messages</b><span class="text-mute small">entre vous, jamais avec les patients</span>
        <button class="btn-close ms-auto" data-close-messages aria-label="Fermer"></button>
    </div>
    <div class="mp-people" data-people></div>
    <div class="mp-log" data-log></div>
    <div class="mp-quick" data-quick>
        @foreach (['Le patient est arrivé.', 'Patient suivant ?', 'Peux-tu le rappeler ?', 'Un retard de 10 min.', 'C’est noté, merci.'] as $q)
            <button type="button">{{ $q }}</button>
        @endforeach
    </div>
    <form class="mp-form" data-msg-form>
        <label class="mp-about" data-about hidden><input type="checkbox" name="about" checked> À propos de <b data-about-name></b></label>
        <div class="d-flex gap-2"><textarea name="body" rows="1" placeholder="Écrire un message…" maxlength="2000" required></textarea><button class="btn btn-primary" aria-label="Envoyer"><x-icon name="send" size="16" /></button></div>
    </form>
</aside>
@stack('scripts')
</body>
</html>
