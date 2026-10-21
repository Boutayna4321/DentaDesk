<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
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

        CarbonImmutable::setLocale('fr');
        Date::setLocale('fr');
    }
}
