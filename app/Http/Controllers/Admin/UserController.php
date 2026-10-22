<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserAddRequest;
use App\Http\Requests\Admin\UserUpdateRequest;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Support\FormStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Kullanıcı yönetimi. Sadece admin rolü erişebilir ("admin" Gate'i, [[AppServiceProvider]]).
 */
class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin');

        $kullanicilar = User::with('rol')->orderBy('id')->get();

        return view('admin.users.index', [
            'kullanicilar' => $kullanicilar,
            'duzenleAcik' => FormStatus::editMap($kullanicilar->pluck('id')),
            'buFormEkle' => FormStatus::isAdd(),
            'roller' => Role::ordered()->get(),
            'yetkiTalepleri' => PermissionRequest::with('user')
                ->where('durum', 'bekliyor')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(UserAddRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $yeni = User::create([
            'name' => $validated['ad'],
            'email' => $validated['eposta'],
            'password' => Hash::make($validated['sifre']),
            'rol_id' => Role::where('slug', $validated['rol'])->value('id'),

            /*
             * Ekrandan verilen rol YERELDİR: merkez eşitlemesi ona dokunmaz
             * (bkz. IdentitySyncer::roluGuncelle). Merkezden açılan hesapta
             * bu işaret kapalıdır, yani varsayılan "merkez ne diyorsa o".
             */
            'rol_yerel' => true,

            'perm_teklif' => $request->boolean('perm_teklif'),
            'perm_bilgi' => $request->boolean('perm_bilgi'),
            'perm_fiyat' => $request->boolean('perm_fiyat'),
        ]);

        app(ActivityRecorder::class)->log(
            'kullanici_ekle',
            "Kullanıcı açıldı: {$yeni->email} · {$yeni->name} · rol ".($yeni->rol?->slug ?? 'rolsüz')
                .' · yetkiler '.$this->permissionSummary($yeni),
            $yeni,
        );

        return redirect()->route('admin.users.index')->with('status', 'Kullanıcı eklendi.');
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $oncekiRol = $user->rol?->slug ?? 'rolsüz';
        $oncekiYetki = $this->permissionSummary($user);
        $oncekiAktif = $user->aktif;

        $user->update([
            'name' => $validated['ad'],
            'email' => $validated['eposta'],
            'rol_id' => Role::where('slug', $validated['rol'])->value('id'),
            'rol_yerel' => true,
            /*
             * `?? null`: alan "nullable" ve form onu hiç göndermeyebiliyor.
             * Doğrudan indislemek "Undefined array key" ile düşüyordu —
             * tarayıcıdaki form alanı her zaman gönderdiği için görünmüyordu.
             */
            'password' => ($validated['yeni_sifre'] ?? null)
                ? Hash::make($validated['yeni_sifre'])
                : $user->password,
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

        $yeniRol = $user->fresh()->rol?->slug ?? 'rolsüz';

        if ($oncekiRol !== $yeniRol) {
            $degisenler[] = "rol {$oncekiRol} → {$yeniRol}";
        }

        if ($oncekiAktif !== $user->aktif) {
            $degisenler[] = $user->aktif ? 'hesap açıldı' : 'hesap kapatıldı';
        }

        if ($oncekiYetki !== ($yeniYetki = $this->permissionSummary($user))) {
            $degisenler[] = "yetkiler {$oncekiYetki} → {$yeniYetki}";
        }

        if ($validated['yeni_sifre'] ?? null) {
            $degisenler[] = 'parola değiştirildi';
        }

        app(ActivityRecorder::class)->log(
            'kullanici_guncelle',
            "Kullanıcı güncellendi: {$user->email}"
                .($degisenler === [] ? ' · değişiklik yok' : ' · '.implode(', ', $degisenler)),
            $user,
        );

        return redirect()->route('admin.users.index')->with('status', 'Kullanıcı güncellendi.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('admin');

        /* Kayıt SİLMEDEN ÖNCE: silinen hesabın adı sonradan okunamaz. */
        app(ActivityRecorder::class)->log(
            'kullanici_sil',
            "Kullanıcı silindi: {$user->email} · {$user->name} · rol ".($user->rol?->slug ?? 'rolsüz'),
        );

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Kullanıcı silindi.');
    }

    public function approvePermissionRequest(PermissionRequest $permissionRequest): RedirectResponse
    {
        $this->authorize('admin');

        $permissionRequest->user->update([
            'perm_teklif' => $permissionRequest->perm_teklif,
            'perm_bilgi' => $permissionRequest->perm_bilgi,
            'perm_fiyat' => $permissionRequest->perm_fiyat,
        ]);

        $permissionRequest->update(['durum' => 'onaylandi']);

        app(ActivityRecorder::class)->log(
            'yetki_talebi_onay',
            "Yetki talebi onaylandı: {$permissionRequest->user->email} · yeni yetkiler "
                .$this->permissionSummary($permissionRequest->user->fresh()),
            $permissionRequest->user,
        );

        return redirect()->route('admin.users.index')->with('status', 'Yetki talebi onaylandı.');
    }

    public function rejectPermissionRequest(PermissionRequest $permissionRequest): RedirectResponse
    {
        $this->authorize('admin');

        $permissionRequest->update(['durum' => 'reddedildi']);

        return redirect()->route('admin.users.index')->with('status', 'Yetki talebi reddedildi.');
    }

    /**
     * Yetkilerin tek satırlık özeti; denetim kaydında "önce/sonra"
     * karşılaştırmak için. Tek yerde üretilir ki iki satır aynı biçimde
     * yazılsın ve karşılaştırma anlamlı olsun.
     */
    private function permissionSummary(User $user): string
    {
        $acik = array_keys(array_filter([
            'teklif' => $user->perm_teklif,
            'bilgi' => $user->perm_bilgi,
            'fiyat' => $user->perm_fiyat,
        ]));

        return $acik === [] ? 'yok' : implode('+', $acik);
    }
}
