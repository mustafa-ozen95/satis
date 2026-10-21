<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\Identity\CenterClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Giriş, çıkış ve parola sıfırlama isteği.
 *
 * Yetkilendirme: bu uçlar BİLEREK `auth` dışında — giriş yapmamış
 * kullanıcının erişebilmesi gerekiyor. `yetkisiz` sayfası oturum içinde ve
 * yalnızca bir mesaj gösteriyor.
 */
class AuthController extends Controller
{
    public function showLogin(CenterClient $merkez): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('teklif.create');
        }

        /* Görünüm kardeş panellerle BİREBİR AYNI dosya; beklediği iki değer bunlar. */
        return view('auth.login', [
            'merkezAcik' => $merkez->acikMi(),
            'merkezAd' => config('identity.ad'),
        ]);
    }

    public function login(LoginRequest $request, ActivityRecorder $kayit): RedirectResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->aktif || ! Auth::attempt($credentials)) {
            /*
             * DENEME KAYDA GEÇER. Kullanıcı adı yazılır, PAROLA YAZILMAZ —
             * denetim kaydını okuyan herkes onu görürdü. Hesabın var olup
             * olmadığı da ayırt edilmez: ekranda tek bir mesaj var, kayıtta
             * iki farklı mesaj olması o ayrımı geri getirirdi.
             */
            $kayit->kaydet('giris_basarisiz', "Başarısız giriş denemesi: {$credentials['email']}");

            /*
             * HATA DOĞRULAMA HATASI OLARAK DÖNER — kardeş panellerdeki gibi.
             * Görünüm 401 ile doğrudan çizilseydi giriş ekranı şablondan ayrı
             * bir değişken (`$error`) taşımak zorunda kalır ve ayrışırdı.
             */
            throw ValidationException::withMessages([
                'email' => 'Bu bilgilerle giriş yapılamadı.',
            ]);
        }

        $request->session()->regenerate();

        $kayit->kaydet('giris', "Panele giriş: {$user->email}", $user, $user);

        return redirect()->route('teklif.create');
    }

    public function logout(Request $request, ActivityRecorder $kayit): RedirectResponse
    {
        /* Kayıt ÇIKIŞTAN ÖNCE: sonrasında kimin çıktığı belli olmaz. */
        if ($kullanici = $request->user()) {
            $kayit->kaydet('cikis', "Panelden çıkış: {$kullanici->email}", $kullanici, $kullanici);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function yetkisiz(): View
    {
        return view('auth.unauthorized');
    }
}
