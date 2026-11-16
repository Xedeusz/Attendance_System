<?php

namespace App\Providers;

use App\Mail\PrivateLocalTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        Mail::extend('private_local', fn () => new PrivateLocalTransport);
        URL::forceRootUrl(config('app.url'));
        View::composer('components.topbar', function (\Illuminate\View\View $view): void {
            $user = auth()->user();
            $view->with('unreadNotices', $user ? $user->unreadNotifications()->when($user->isSupervisor(), fn ($query) => $query->where('data->category', 'leave'))->count() : 0);
        });
    }
}
