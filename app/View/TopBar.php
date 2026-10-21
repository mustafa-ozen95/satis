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
                'url' => route('teklif.create'),
                'label' => 'Teklifler',
                'active' => request()->routeIs('teklif.*'),
            ],
            /*
             * KAYNAKLAR: bilgi bankası ve fiyat listesi tek girdide, iki sekmede.
             * İkisi de Teklif AI'ın beslendiği kaynak; menüde ayrı ayrı durunca
             * şerit telefonda sığmıyordu.
             */
            [
                'url' => route('bilgi-bankasi.index'),
                'label' => 'Kaynaklar',
                'active' => request()->routeIs('bilgi-bankasi.*', 'fiyatlar.*'),
            ],

            $kullanici?->isAdmin() ? [
                'url' => route('admin.kullanicilar.index'),
                'label' => 'Kullanıcılar',
                'active' => request()->routeIs('admin.kullanicilar.*'),
            ] : null,
            $kullanici?->isAdmin() ? [
                'url' => route('admin.kayitlar.denetim'),
                'label' => 'Kayıtlar',
                /* İstatistikler de bu ailenin sekmesi (bkz. components/logs/tabs). */
                'active' => request()->routeIs('admin.kayitlar.*', 'admin.istatistikler.*'),
            ] : null,
            $kullanici?->isAdmin() ? [
                'url' => route('admin.ayarlar.index'),
                'label' => 'Ayarlar',
                /* API jetonları Ayarlar'ın sekmesi; şeritte ayrı girdisi yok. */
                'active' => request()->routeIs('admin.ayarlar.*', 'admin.api-jetonlari.*'),
            ] : null,
        ]));

        $view->with(['kullanici' => $kullanici, 'bolumler' => $bolumler]);
    }
}
