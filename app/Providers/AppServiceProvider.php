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
        $appUrl = config('app.url');
        URL::forceRootUrl($appUrl);
        if (parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        View::composer('components.topbar', function (\Illuminate\View\View $view): void {
            $user = auth()->user();
            $view->with('unreadNotices', $user?->workspaceNotifications()->unread()->count() ?? 0);
        });
    }
}
