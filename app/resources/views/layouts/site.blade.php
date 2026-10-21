<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'snan. · Cabinet dentaire à Maârif, Casablanca')</title>
    <meta name="description" content="Cabinet dentaire du {{ config('cabinet.doctor') }} à Maârif, Casablanca. Réservez votre rendez-vous en ligne ou écrivez à notre assistant en darija, français ou arabe.">
    <meta name="theme-color" content="#0c2350">
    <meta property="og:type" content="website">
    <meta property="og:title" content="snan. · Cabinet dentaire à Maârif, Casablanca">
    <meta property="og:description" content="Réservez en ligne sur de vrais créneaux, ou écrivez à l’assistant en darija, français, arabe ou anglais.">
    <meta property="og:image" content="{{ url('/img/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%231f56d6'/%3E%3Cpath d='M8.5 5C6 5 5 6.8 5 9c0 2.9 1.1 4.2 1.7 7.2.3 1.7.8 2.8 1.7 2.8s1.1-1.8 1.5-3.4c.3-1.1.7-1.8 2.1-1.8s1.8.7 2.1 1.8c.4 1.6.6 3.4 1.5 3.4s1.4-1.1 1.7-2.8C17.9 13.2 19 11.9 19 9c0-2.2-1-4-3.5-4-1.8 0-2.3 1-3.5 1S10.3 5 8.5 5Z' fill='white'/%3E%3C/svg%3E">
    {{ Vite::fonts() }}
    @vite(['resources/scss/site.scss', 'resources/js/site.js'])
</head>
<body>
    @yield('content')
</body>
</html>
