<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\NameRequest;
use App\Http\Requests\Profile\PasswordRequest;
use App\Models\PermissionRequest;
use App\Support\FormStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Oturum açan kullanıcının kendi hesabı: kullanıcı adı, parola ve yetki
 * talebi.
 *
 * Yetkilendirme: bütün uçlar `auth` altında ve hedef İSTEKTEN DEĞİL
 * oturumdan geliyor — kişi yalnızca kendi hesabına dokunabiliyor, başkasının
 * kaydını göstermenin bir yolu yok. Rol ve gerçek yetkiler (perm_*) burada DEĞİŞTİRİLEMEZ - kullanıcı
 * yalnızca ne istediğini işaretleyip admin onayına gönderebilir
 * (bkz. PermissionRequest, admin/kullanicilar sayfasında onaylanır/reddedilir).
 */
class ProfileController extends Controller
{
    public function show(): View
    {
        $bekleyenTalep = auth()->user()
            ->permissionRequests()
            ->where('durum', 'bekliyor')
            ->latest('created_at')
            ->first();

        return view('profile.index', [
            'bekleyenTalep' => $bekleyenTalep,
            'buFormAd' => FormStatus::mi('ad'),
            'buFormSifre' => FormStatus::mi('sifre'),
        ]);
    }

    public function adGuncelle(NameRequest $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        $user->update(['name' => $validated['ad']]);

        return redirect()->route('profil.show')->with('status', 'Adınız güncellendi.');
    }

    public function sifreGuncelle(PasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update([
            'password' => Hash::make($validated['yeni_sifre']),
        ]);

        return redirect()->route('profil.show')->with('status', 'Parolanız güncellendi.');
    }

    public function yetkiTalebiGonder(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->permissionRequests()->where('durum', 'bekliyor')->exists()) {
            return redirect()->route('profil.show')
                ->with('error', 'Zaten onay bekleyen bir yetki talebiniz var.');
        }

        PermissionRequest::create([
            'user_id' => $user->id,
            'perm_teklif' => $request->boolean('perm_teklif'),
            'perm_bilgi' => $request->boolean('perm_bilgi'),
            'perm_fiyat' => $request->boolean('perm_fiyat'),
        ]);

        return redirect()->route('profil.show')->with('status', 'Yetki talebiniz yöneticinize gönderildi.');
    }
}
