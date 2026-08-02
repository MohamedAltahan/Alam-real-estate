<?php

namespace App\Providers;

use App\Support\NotificationFeed;
use Illuminate\Support\Facades\Gate;
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
        // مدير النظام يملك كل الصلاحيات تلقائياً
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // قائمة الإشعارات في الشريط العلوي للداشبورد
        // (طلبات X-Fragment لا ترسم الشريط العلوي، فنوفّر استعلاماتها)
        View::composer('layouts.dashboard', function ($view) {
            if (request()->header('X-Fragment') === '1') {
                return;
            }

            $view->with([
                'feedItems' => NotificationFeed::items(),
                'feedUnread' => NotificationFeed::unreadCount(),
            ]);
        });
    }
}
