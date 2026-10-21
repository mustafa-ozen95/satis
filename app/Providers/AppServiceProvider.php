<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Api\ApiTokenContext;
use App\Services\Api\ApiTokenGuard;
use App\Services\Api\TokenAbilityGate;
use App\Services\Settings\SettingsRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * İSTEK BAŞINA TEK: bekçi jetonu buraya yazıyor, yetki kapısı buradan
         * okuyor. Singleton olmasaydı ikisi ayrı örneklere bakar ve jetonun
         * dar listesi hiçbir şey ifade etmezdi.
         */
        $this->app->singleton(ApiTokenContext::class);

        //
    }

    /**
     * Bootstrap any application services.
     *
     * Yetki kapıları: admin rolü her zaman geçer, "user" rolü ince ayarlı
     * perm_* sütunlarına bağlıdır.
     */
    public function boot(): void
    {
        /*
         * Panelden değiştirilen ayarları config'in ÜZERİNE BİNDİR.
         *
         * Her istekte uygulanır: satırı olmayan ayar varsayılanında kalır,
         * bir ayarı sıfırlamak satırı silmektir. config:cache sırasında
         * atlanır — yoksa veritabanındaki değerler dosyaya gömülür ve ayar
         * silindiğinde varsayılana dönmez (bkz. SettingsRepository::apply).
         */
        $this->app->make(SettingsRepository::class)->apply();

        /*
         * SAYFALAMA PANELİN KENDİ ŞERİDİYLE ÇİZİLİR.
         *
         * Laravel'in varsayılanı `pagination::tailwind`; bu panelde Tailwind
         * yok ve o şablonun SVG okları sınırsız boyda çizilir. Şerit ve
         * biçemi kardeş panellerle birebir aynı (bkz. TASARIM.md).
         */
        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.default');

        /*
         * "Authorization: Bearer ..." başlığındaki jetonu kullanıcıya çeviren
         * bekçi. Oturum çerezi kabul edilmez: API ayrı bir kapıdır.
         */
        Auth::viaRequest('api-token', $this->app->make(ApiTokenGuard::class));

        /*
         * JETON YETKİSİ ÖNCE SORULUR.
         *
         * API isteği bir kullanıcı adına geliyor; jetonun listesi o
         * kullanıcının yetkisini yalnızca DARALTIR. Tarayıcıdan gelen
         * isteklerde bağlamda jeton olmadığı için hiç devreye girmez.
         */
        /*
         * SINIR JETON BAŞINA, adres başına değil: aynı adresten çalışan iki
         * jeton birbirini yavaşlatmamalı. Jeton yoksa adrese düşülür.
         */
        RateLimiter::for('api', function (Request $request): Limit {
            $jeton = $this->app->make(ApiTokenContext::class)->get();

            return Limit::perMinute((int) config('api.rate_limit'))
                ->by($jeton?->prefix ?? $request->ip());
        });

        Gate::before(fn (User $user, string $ability, array $arguments = []): ?bool => $this->app
            ->make(TokenAbilityGate::class)
            ->check($user, $ability, $arguments));

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        /* API yetenekleri: "kaynak:aksiyon". Panelin kendi kapılarıyla aynı yere bakar. */
        Gate::define('fiyat:view', fn (User $user) => $user->isAdmin() || $user->perm_fiyat);
        Gate::define('bilgi:view', fn (User $user) => $user->isAdmin() || $user->perm_bilgi);
        Gate::define('teklif:view', fn (User $user) => $user->isAdmin() || $user->perm_teklif);
        Gate::define('teklif:create', fn (User $user) => $user->isAdmin() || $user->perm_teklif);
        Gate::define('perm_teklif', fn (User $user) => $user->isAdmin() || $user->perm_teklif);
        Gate::define('perm_bilgi', fn (User $user) => $user->isAdmin() || $user->perm_bilgi);
        Gate::define('perm_fiyat', fn (User $user) => $user->isAdmin() || $user->perm_fiyat);

        // Her view'da kullanılabilen ortak degiskenler (oturum kullanıcısı, bekleyen sifre istegi sayisi).
        // Bu composer bir sayfada birden fazla view/component render edilirken
        // (örn. x-icon) tekrar tekrar tetiklenir - istek başına tek sorgu için
        // static cache kullanılıyor.
        View::composer('*', function ($view) {
            static $cache = null;

            if ($cache === null) {
                $user = Auth::user();

                $cache = [
                    'navUser' => $user,
                ];
            }

            $view->with($cache);
        });
    }
}
