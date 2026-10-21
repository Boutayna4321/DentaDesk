<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Medical notes and the odontogram are for the dentist, not the front desk.
        Gate::define('medical', fn (User $user) => $user->isDentist());
        // The dentist runs the practice: accounts, invoices, prices, settings.
        Gate::define('admin', fn (User $user) => $user->isDentist());

        // One budget per action: chatting with the assistant must not use up the booking budget.
        RateLimiter::for('public-api', fn (Request $r) => Limit::perMinute(120)->by('api|'.$r->ip()));
        RateLimiter::for('booking', fn (Request $r) => Limit::perMinute(6)->by('booking|'.$r->ip()));
        RateLimiter::for('assistant', fn (Request $r) => Limit::perMinute(30)->by('assistant|'.$r->ip()));
        RateLimiter::for('messages', fn (Request $r) => Limit::perMinute(30)->by('messages|'.$r->user()?->id));

        CarbonImmutable::setLocale('fr');
        Date::setLocale('fr');
    }
}
