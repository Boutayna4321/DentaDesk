<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Connexion · DentaDesk cabinet</title>
    {{ Vite::fonts() }}
    @vite(['resources/scss/dashboard.scss'])
</head>
<body>
<div class="login">
    <div class="art"><div class="quote">« On explique avant de soigner. »<small>Espace du cabinet · rendez-vous, patients, assistant</small></div></div>
    <div class="form-side">
        <form method="post" action="{{ url('login') }}">
            @csrf
            <div class="d-flex align-items-center gap-2 mb-4"><x-icon name="tooth" size="28" stroke="#1f56d6" /><b class="fs-5">DentaDesk</b><span class="text-mute small ms-1">espace cabinet</span></div>
            <h1 class="h4 mb-1">Bonjour</h1>
            <p class="text-mute small mb-4">Connectez-vous pour voir la journée.</p>
            @error('email')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <label class="form-label small fw-bold" for="email">Email</label>
            <input class="form-control mb-3" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            <label class="form-label small fw-bold" for="password">Mot de passe</label>
            <input class="form-control mb-3" id="password" name="password" type="password" required autocomplete="current-password">
            <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label small" for="remember">Rester connecté</label></div>
            <button class="btn btn-primary w-100">Se connecter</button>
            @env('local')
                <p class="small text-mute mt-4 mb-0">Démo : <span class="mono">dentiste@dentadesk.test</span> ou <span class="mono">secretaire@dentadesk.test</span>, mot de passe dans le README.</p>
            @endenv
        </form>
    </div>
</div>
</body>
</html>
