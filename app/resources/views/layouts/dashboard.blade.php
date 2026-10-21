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
        ['dash.requests', 'Demandes', 'chat', $open ?: null],
        ['dash.patients', 'Patients', 'child', null],
        ['dash.assistant', 'Assistant AI', 'sparkle', null],
    ];
@endphp
<div class="shell">
    <aside class="side">
        <div class="brand"><x-icon name="tooth" size="22" stroke="#fff" />snan.</div>
        @foreach ($nav as [$route, $label, $icon, $badge])
            <a class="item {{ request()->routeIs($route.'*') ? 'on' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon" size="17" />{{ $label }}@if ($badge)<em>{{ $badge }}</em>@endif</a>
        @endforeach
        <div class="sep">CABINET</div>
        <a class="item {{ request()->routeIs('dash.settings') ? 'on' : '' }}" href="{{ route('dash.settings') }}"><x-icon name="doc" size="17" />Horaires &amp; soins</a>
        <a class="item" href="{{ route('home') }}" target="_blank"><x-icon name="pin" size="17" />Voir le site</a>
        <div class="me">
            <img src="/img/doctor.jpg" alt="">
            <div><b>{{ auth()->user()->name }}</b>{{ auth()->user()->isDentist() ? 'Dentiste' : 'Secrétariat' }}</div>
            <form method="post" action="{{ route('logout') }}">@csrf<button>Sortir</button></form>
        </div>
    </aside>
    <main class="main">
        @if (session('ok'))<div class="alert alert-success py-2 small fw-semibold">{{ session('ok') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger py-2 small fw-semibold">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
