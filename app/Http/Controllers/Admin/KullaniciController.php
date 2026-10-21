<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KullaniciEkleRequest;
use App\Http\Requests\Admin\KullaniciGuncelleRequest;
use App\Models\PermissionRequest;
use App\Models\Rol;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Kullanıcı yönetimi. Sadece admin rolü erişebilir ("admin" Gate'i, [[AppServiceProvider]]).
 */
class KullaniciController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin');

        return view('admin.kullanicilar.index', [
            'kullanicilar' => User::with('rol')->orderBy('id')->get(),
            'roller' => Rol::sirali()->get(),
            'yetkiTalepleri' => PermissionRequest::with('user')
                ->where('durum', 'bekliyor')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(KullaniciEkleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $yeni = User::create([
            'name' => $validated['ad'],
            'email' => $validated['eposta'],
            'password' => Hash::make($validated['sifre']),
            'rol_id' => Rol::where('slug', $validated['rol'])->value('id'),

            /*
             * Ekrandan verilen rol YERELDİR: merkez eşitlemesi ona dokunmaz
             * (bkz. KimlikEsitleyici::roluGuncelle). Merkezden açılan hesapta
             * bu işaret kapalıdır, yani varsayılan "merkez ne diyorsa o".
             */
            'rol_yerel' => true,

            'perm_teklif' => $request->boolean('perm_teklif'),
            'perm_bilgi' => $request->boolean('perm_bilgi'),
            'perm_fiyat' => $request->boolean('perm_fiyat'),
        ]);

        app(ActivityRecorder::class)->kaydet(
            'kullanici_ekle',
            "Kullanıcı açıldı: {$yeni->email} · {$yeni->name} · rol ".($yeni->rol?->slug ?? 'rolsüz')
                .' · yetkiler '.$this->yetkiOzeti($yeni),
            $yeni,
        );

        return redirect()->route('admin.kullanicilar.index')->with('status', 'Kullanıcı eklendi.');
    }

    public function update(KullaniciGuncelleRequest $request, User $kullanici): RedirectResponse
    {
        $validated = $request->validated();

        $oncekiRol = $kullanici->rol?->slug ?? 'rolsüz';
        $oncekiYetki = $this->yetkiOzeti($kullanici);
        $oncekiAktif = $kullanici->aktif;

        $kullanici->update([
            'name' => $validated['ad'],
            'email' => $validated['eposta'],
            'rol_id' => Rol::where('slug', $validated['rol'])->value('id'),
            'rol_yerel' => true,
            /*
             * `?? null`: alan "nullable" ve form onu hiç göndermeyebiliyor.
             * Doğrudan indislemek "Undefined array key" ile düşüyordu —
             * tarayıcıdaki form alanı her zaman gönderdiği için görünmüyordu.
             */
            'password' => ($validated['yeni_sifre'] ?? null)
                ? Hash::make($validated['yeni_sifre'])
                : $kullanici->password,
            'aktif' => $request->boolean('aktif'),
            'perm_teklif' => $request->boolean('perm_teklif'),
            'perm_bilgi' => $request->boolean('perm_bilgi'),
            'perm_fiyat' => $request->boolean('perm_fiyat'),
        ]);

        /*
         * NE DEĞİŞTİĞİ YAZILIR, yeni hâli değil. "Kullanıcı güncellendi"
         * satırı denetimde hiçbir soruyu yanıtlamıyor; rolün ya da yetkinin
         * ne zaman kimin eliyle genişlediği tam olarak bu satırdan okunur.
         */
        $degisenler = [];

        $yeniRol = $kullanici->fresh()->rol?->slug ?? 'rolsüz';

        if ($oncekiRol !== $yeniRol) {
            $degisenler[] = "rol {$oncekiRol} → {$yeniRol}";
        }

        if ($oncekiAktif !== $kullanici->aktif) {
            $degisenler[] = $kullanici->aktif ? 'hesap açıldı' : 'hesap kapatıldı';
        }

        if ($oncekiYetki !== ($yeniYetki = $this->yetkiOzeti($kullanici))) {
            $degisenler[] = "yetkiler {$oncekiYetki} → {$yeniYetki}";
        }

        if ($validated['yeni_sifre'] ?? null) {
            $degisenler[] = 'parola değiştirildi';
        }

        app(ActivityRecorder::class)->kaydet(
            'kullanici_guncelle',
            "Kullanıcı güncellendi: {$kullanici->email}"
                .($degisenler === [] ? ' · değişiklik yok' : ' · '.implode(', ', $degisenler)),
            $kullanici,
        );

        return redirect()->route('admin.kullanicilar.index')->with('status', 'Kullanıcı güncellendi.');
    }

    public function destroy(User $kullanici): RedirectResponse
    {
        $this->authorize('admin');

        /* Kayıt SİLMEDEN ÖNCE: silinen hesabın adı sonradan okunamaz. */
        app(ActivityRecorder::class)->kaydet(
            'kullanici_sil',
            "Kullanıcı silindi: {$kullanici->email} · {$kullanici->name} · rol ".($kullanici->rol?->slug ?? 'rolsüz'),
        );

        $kullanici->delete();

        return redirect()->route('admin.kullanicilar.index')->with('status', 'Kullanıcı silindi.');
    }

    public function onaylaYetkiTalebi(PermissionRequest $yetkiTalebi): RedirectResponse
    {
        $this->authorize('admin');

        $yetkiTalebi->user->update([
            'perm_teklif' => $yetkiTalebi->perm_teklif,
            'perm_bilgi' => $yetkiTalebi->perm_bilgi,
            'perm_fiyat' => $yetkiTalebi->perm_fiyat,
        ]);

        $yetkiTalebi->update(['durum' => 'onaylandi']);

        app(ActivityRecorder::class)->kaydet(
            'yetki_talebi_onay',
            "Yetki talebi onaylandı: {$yetkiTalebi->user->email} · yeni yetkiler "
                .$this->yetkiOzeti($yetkiTalebi->user->fresh()),
            $yetkiTalebi->user,
        );

        return redirect()->route('admin.kullanicilar.index')->with('status', 'Yetki talebi onaylandı.');
    }

    public function reddetYetkiTalebi(PermissionRequest $yetkiTalebi): RedirectResponse
    {
        $this->authorize('admin');

        $yetkiTalebi->update(['durum' => 'reddedildi']);

        return redirect()->route('admin.kullanicilar.index')->with('status', 'Yetki talebi reddedildi.');
    }

    /**
     * Yetkilerin tek satırlık özeti; denetim kaydında "önce/sonra"
     * karşılaştırmak için. Tek yerde üretilir ki iki satır aynı biçimde
     * yazılsın ve karşılaştırma anlamlı olsun.
     */
    private function yetkiOzeti(User $kullanici): string
    {
        $acik = array_keys(array_filter([
            'teklif' => $kullanici->perm_teklif,
            'bilgi' => $kullanici->perm_bilgi,
            'fiyat' => $kullanici->perm_fiyat,
        ]));

        return $acik === [] ? 'yok' : implode('+', $acik);
    }
}
