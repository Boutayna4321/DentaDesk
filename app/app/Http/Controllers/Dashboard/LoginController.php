<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('dashboard.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        // Five tries per email and address, then a pause: slows password guessing without a 429 page.
        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.'])->onlyInput('email');
        }
        // A deactivated account fails like a wrong password would, plus a clear reason.
        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            $inactive = \App\Models\User::where('email', $credentials['email'])->where('is_active', false)->exists();

            return back()->withErrors(['email' => $inactive
                ? 'Ce compte est désactivé. Demandez à la dentiste de le réactiver.'
                : 'Email ou mot de passe incorrect.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dash.today'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
