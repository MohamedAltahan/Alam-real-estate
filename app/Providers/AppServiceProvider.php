<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ClientViewing;
use App\Support\NotificationFeed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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

        // بدون «عرض كل العملاء» لا يُفتح عميل غيره (ولا معايناته) حتى بالرابط المباشر — 404.
        // من لا يملك «عرض العملاء» أصلاً يتركه الربط لميدلوير الصلاحية (403).
        $ownOnly = fn () => ($user = auth()->user()) && $user->can('clients.view') && ! $user->can('clients.view_all');
        Route::bind('client', fn ($value) => Client::query()
            ->whereKey($value)
            ->when($ownOnly(), fn ($q) => $q->visibleTo(auth()->user()))
            ->firstOrFail());
        Route::bind('viewing', fn ($value) => ClientViewing::query()
            ->whereKey($value)
            ->when($ownOnly(), fn ($q) => $q->whereHas('client', fn ($c) => $c->visibleTo(auth()->user())))
            ->firstOrFail());

        // قائمة الإشعارات في الشريط العلوي للداشبورد
        // (طلبات X-Fragment لا ترسم الشريط العلوي، فنوفّر استعلاماتها)
        View::composer('layouts.dashboard', function ($view) {
            if (request()->header('X-Fragment') === '1') {
                return;
            }

            $user = request()->user();

            $view->with([
                'feedItems' => NotificationFeed::items($user),
                'feedUnread' => NotificationFeed::unreadCount($user),
            ]);
        });
    }
}
