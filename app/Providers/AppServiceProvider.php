<?php

namespace App\Providers;

use App\Support\SiteAppearance;
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
        View::composer('app', function ($view) {
            $view->with('sgcAppearance', SiteAppearance::resolved(SiteAppearance::forRequest(request())));
        });
        View::share('sgcAppearance', SiteAppearance::resolved(SiteAppearance::defaults()));
    }
}
