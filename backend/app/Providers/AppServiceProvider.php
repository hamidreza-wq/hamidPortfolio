<?php

namespace App\Providers;

use App\Models\User;
use App\Services\DestructiveDatabaseGuard;
use App\Support\Roles;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        Event::listen(CommandStarting::class, [DestructiveDatabaseGuard::class, 'handle']);

        // One gate per role permission, for @can('content') and friends in views.
        foreach (array_keys(Roles::PERMISSIONS) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Named, so search-as-you-type keeps its own counter instead of sharing
        // the per-user counter that plain throttle:N,M routes use.
        RateLimiter::for('admin-search', fn (Request $request) => Limit::perMinute(120)->by('admin-search:'.$request->user()?->id));
    }
}
