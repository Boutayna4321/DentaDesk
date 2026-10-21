<div class="row g-2">
    <div class="col-md-4"><label class="form-label small text-mute mb-0">Nom complet</label><input class="form-control form-control-sm" name="name" value="{{ old('name', $u?->name) }}" required></div>
    <div class="col-md-4"><label class="form-label small text-mute mb-0">Email</label><input class="form-control form-control-sm" type="email" name="email" value="{{ old('email', $u?->email) }}" required></div>
    <div class="col-md-4"><label class="form-label small text-mute mb-0">Téléphone</label><input class="form-control form-control-sm" name="phone" value="{{ old('phone', $u?->phone) }}"></div>
    <div class="col-md-4"><label class="form-label small text-mute mb-0">Rôle</label>
        <select class="form-select form-select-sm" name="role" @disabled($u?->is(auth()->user()))>
            @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected(old('role', $u?->role ?? 'secretary') === $k)>{{ $v }}</option>@endforeach
        </select>
        @if ($u?->is(auth()->user()))<input type="hidden" name="role" value="{{ $u->role }}">@endif
    </div>
    <div class="col-md-4"><label class="form-label small text-mute mb-0">{{ $u ? 'Nouveau mot de passe (laisser vide pour garder)' : 'Mot de passe' }}</label>
        <input class="form-control form-control-sm" type="password" name="password" autocomplete="new-password" @required(! $u) minlength="8"></div>
    <div class="col-md-4 d-flex align-items-end"><span class="small text-mute">8 caractères minimum, lettres et chiffres.</span></div>
</div>
