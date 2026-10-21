<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\ApiToken;
use Illuminate\Support\Facades\Gate;

/**
 * Ayarlar şeridi.
 *
 * TEK LİSTE: ayar sayfası ve API jetonları aynı şeritte duruyor ve ikisi de
 * buradan besleniyor. Şerit her ekranda ayrı yazılsaydı biri değişince
 * diğeri sessizce eski kalır, kullanıcı da bir sekmeyi bir ekranda görüp
 * diğerinde göremezdi.
 *
 * Göremeyeceği sekme ŞERİTTE DE DURMAZ: erişemeyeceği bir bağlantı 403
 * üretmekten başka işe yaramaz.
 *
 * Kardeş panellerdeki sınıfın aynısı.
 */
class SettingsTabs
{
    public function __construct(private SettingsCatalog $catalog) {}

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function all(?string $current = null): array
    {
        $tabs = [];

        foreach ($this->catalog->groups() as $key => $group) {
            $tabs[] = [
                'label' => $group['label'],
                'url' => route('admin.ayarlar.index', ['grup' => $key]),
                'active' => $key === $current,
            ];
        }

        if (Gate::allows('viewAny', ApiToken::class)) {
            $tabs[] = [
                'label' => 'API jetonları',
                'url' => route('admin.api-jetonlari.index'),
                'active' => $current === 'api',
            ];
        }

        /*
         * Sertifika da bu şeridin sekmesi: panelin adresine dair bir ayar ve
         * yılda birkaç kez bakılıyor — kendi başına bir bölüm olacak kadar
         * büyük değil.
         */
        if (Gate::allows('admin')) {
            $tabs[] = [
                'label' => 'Sertifika',
                'url' => route('admin.sertifika.index'),
                'active' => $current === 'sertifika',
            ];
            $tabs[] = [
                'label' => 'Veritabanı',
                'url' => route('admin.veritabani.index'),
                'active' => $current === 'veritabani',
            ];
            $tabs[] = [
                'label' => 'Güncelleme',
                'url' => route('admin.guncelleme.index'),
                'active' => $current === 'guncelleme',
            ];
        }

        return $tabs;
    }
}
