<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title') · snan. cabinet</title>
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
@endphp
<div class="shell">
    <aside class="side">
        <div class="brand"><x-icon name="tooth" size="22" stroke="#fff" />snan.</div>
        @foreach ($nav as [$route, $label, $icon, $badge])
            <a class="item {{ request()->routeIs($route.'*') ? 'on' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon" size="17" />{{ $label }}@if ($badge !== null)<em data-badge="{{ $route }}" @if (! $badge) hidden @endif>{{ $badge }}</em>@endif</a>
        @endforeach
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
@stack('scripts')
</body>
</html>
