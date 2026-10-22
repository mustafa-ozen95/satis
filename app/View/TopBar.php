<?php

declare(strict_types=1);

namespace App\View;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Üst çubuğun bölüm listesi.
 *
 * Liste TEK YERDE ve görünürlük yetkiye bağlı: yönetici olmayanın göremeyeceği
 * bölümün bağlantısı 403 üretmekten başka işe yaramaz. Hesap burada yapılır;
 * şablon yalnız hazır listeyi çizer.
 */
class TopBar
{
    public function compose(View $view): void
    {
        $kullanici = Auth::user();

        $bolumler = array_values(array_filter([
            [
                'url' => route('quote.create'),
                'label' => 'Teklifler',
                'active' => request()->routeIs('quote.*'),
            ],
            /*
             * KAYNAKLAR: bilgi bankası ve fiyat listesi tek girdide, iki sekmede.
             * İkisi de Teklif AI'ın beslendiği kaynak; menüde ayrı ayrı durunca
             * şerit telefonda sığmıyordu.
             */
            [
                'url' => route('knowledge-base.index'),
                'label' => 'Kaynaklar',
                'active' => request()->routeIs('knowledge-base.*', 'prices.*'),
            ],

            $kullanici?->isAdmin() ? [
                'url' => route('admin.users.index'),
                'label' => 'Kullanıcılar',
                'active' => request()->routeIs('admin.users.*'),
            ] : null,
            $kullanici?->isAdmin() ? [
                'url' => route('admin.logs.audit'),
                'label' => 'Kayıtlar',
                /* İstatistikler de bu ailenin sekmesi (bkz. components/logs/tabs). */
                'active' => request()->routeIs('admin.logs.*', 'admin.statistics.*'),
            ] : null,
            $kullanici?->isAdmin() ? [
                'url' => route('admin.settings.index'),
                'label' => 'Ayarlar',
                /* API jetonları Ayarlar'ın sekmesi; şeritte ayrı girdisi yok. */
                'active' => request()->routeIs('admin.settings.*', 'admin.api-tokens.*'),
            ] : null,
        ]));

        $view->with(['kullanici' => $kullanici, 'bolumler' => $bolumler]);
    }
}
