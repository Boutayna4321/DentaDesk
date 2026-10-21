<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Accounts, managed by the dentist. Delete only what has no history; otherwise deactivate. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('dashboard.users', [
            'users' => User::orderByDesc('is_active')->orderBy('name')->get()->each(fn (User $u) => $u->setAttribute('deletable', ! $u->hasHistory())),
            'log' => ActivityLog::with('user')->latest('created_at')->take(25)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::create($data + ['is_active' => true]);
        ActivityLog::record('user.created', "Compte créé : {$user->name} ({$user->role_label})", $user);

        return back()->with('ok', "Compte créé pour {$user->name}. Communiquez-lui son mot de passe en main propre.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if ($user->is($request->user()) && $data['role'] !== $user->role) {
            return back()->with('error', 'Vous ne pouvez pas changer votre propre rôle.');
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);
        ActivityLog::record('user.updated', "Compte modifié : {$user->name}".(isset($data['password']) ? ' (nouveau mot de passe)' : ''), $user);

        return back()->with('ok', 'Compte mis à jour.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($user->is_active && $user->isDentist() && User::where('role', 'dentist')->where('is_active', true)->count() === 1) {
            return back()->with('error', 'Il faut garder au moins un compte dentiste actif.');
        }
        $user->update(['is_active' => ! $user->is_active]);
        ActivityLog::record($user->is_active ? 'user.activated' : 'user.deactivated',
            ($user->is_active ? 'Compte réactivé : ' : 'Compte désactivé : ').$user->name, $user);

        return back()->with('ok', $user->is_active ? "{$user->name} peut de nouveau se connecter." : "{$user->name} ne peut plus se connecter.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        if ($user->hasHistory()) {
            return back()->with('error', "{$user->name} a déjà travaillé dans le logiciel : désactivez le compte plutôt que de le supprimer, pour garder l’historique.");
        }
        ActivityLog::record('user.deleted', "Compte supprimé : {$user->name}");
        $user->delete();

        return back()->with('ok', 'Compte supprimé.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ]);
    }
}
