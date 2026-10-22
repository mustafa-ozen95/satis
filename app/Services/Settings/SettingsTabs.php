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
                'url' => route('admin.settings.index', ['group' => $key]),
                'active' => $key === $current,
            ];
        }

        if (Gate::allows('viewAny', ApiToken::class)) {
            $tabs[] = [
                'label' => 'API jetonları',
                'url' => route('admin.api-tokens.index'),
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
                'url' => route('admin.certificate.index'),
                'active' => $current === 'certificate',
            ];
            $tabs[] = [
                'label' => 'Veritabanı',
                'url' => route('admin.database.index'),
                'active' => $current === 'database',
            ];
            $tabs[] = [
                'label' => 'Güncelleme',
                'url' => route('admin.update.index'),
                'active' => $current === 'update',
            ];
        }

        return $tabs;
    }
}
