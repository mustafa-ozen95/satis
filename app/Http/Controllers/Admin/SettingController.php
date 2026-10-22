<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SettingsRequest;
use App\Services\ActivityRecorder;
use App\Services\Settings\SettingsCatalog;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Ayarlar — yalnızca yönetici.
 *
 * Gruplar sekmedir; her grup kendi alanlarını katalogdan çizer. Değer veritabanında durur ve
 * config'in ÜZERİNE BİNDİRİLİR; satırı olmayan ayar varsayılanında kalır.
 *
 * Yetkilendirme: HasMiddleware ile `can:admin`.
 */
class SettingController extends Controller implements HasMiddleware
{
    public function __construct(
        private SettingsCatalog $katalog,
        private SettingsRepository $ayarlar,
        private SettingsTabs $sekmeler,
        private ActivityRecorder $kayit,
    ) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('can:admin')];
    }

    public function index(Request $request): View
    {
        $group = (string) $request->query('group', '');
        $group = in_array($group, $this->katalog->groupKeys(), true)
            ? $group
            : $this->katalog->groupKeys()[0];

        return view('settings.index', [
            'group' => $group,
            'definition' => $this->katalog->group($group),
            'settings' => $this->katalog->definitionsFor($group),
            'values' => collect($this->katalog->definitionsFor($group))
                ->mapWithKeys(fn ($tanim): array => [$tanim->key => $this->displayValue($tanim->key)])
                ->all(),
            'tabs' => $this->sekmeler->all($group),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $degisenler = $this->ayarlar->put($request->payload(), $request->user());

        /*
         * NE DEĞİŞTİĞİ yazılır, yeni hâli değil. "Ayarlar güncellendi" satırı
         * denetimde hiçbir soruyu yanıtlamıyor; bir ayarın ne zaman kimin
         * eliyle gevşediği tam olarak bu satırdan okunur.
         */
        if ($degisenler !== []) {
            $ozet = collect($degisenler)
                ->map(fn (array $d, string $anahtar): string => sprintf(
                    '%s: %s → %s',
                    $anahtar,
                    $this->text($d['from']),
                    $this->text($d['to']),
                ))
                ->implode(' · ');

            $this->kayit->log('ayar_degisti', $ozet);
        }

        return redirect()
            ->route('admin.settings.index', ['group' => $request->group()])
            ->with('status', $degisenler === [] ? 'Değişiklik yok.' : 'Ayarlar kaydedildi.');
    }

    /**
     * Ekranda gösterilecek değer.
     *
     * Dizi tutan bir ayar forma virgüllü metin olarak iner; geri çevrimi
     * okuyan taraf yapar. Diziyi ekranda dizi gibi göstermek, kullanıcıya
     * PHP söz dizimi yazdırmak olurdu.
     */
    private function displayValue(string $anahtar): mixed
    {
        $deger = $this->ayarlar->current($anahtar);

        return is_array($deger) ? implode(',', $deger) : $deger;
    }

    private function text(mixed $deger): string
    {
        if (is_bool($deger)) {
            return $deger ? 'açık' : 'kapalı';
        }

        return is_array($deger) ? implode(',', $deger) : (string) $deger;
    }
}
