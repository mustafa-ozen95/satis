<?php

use App\Http\Controllers\Admin\ApiJetonController;
use App\Http\Controllers\Admin\AyarController;
use App\Http\Controllers\Admin\GuncellemeController;
use App\Http\Controllers\Admin\IstatistikController;
use App\Http\Controllers\Admin\KayitController;
use App\Http\Controllers\Admin\KullaniciController;
use App\Http\Controllers\Admin\SertifikaController;
use App\Http\Controllers\Admin\VeritabaniController;
use App\Http\Controllers\Auth\MerkezGirisController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BilgiBankasiController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FiyatlarController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

// Guvenlik dokumanindaki kural: "Her rota ya auth altinda olacak ya da
// neden olmadigi yorumla aciklanacak." Giris rotalari
// kasitli olarak auth disinda - giris yapmamis kullanicinin erisebilmesi
// gerekiyor.

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

/*
 * KİMLİK MERKEZİ İLE GİRİŞ.
 *
 * Parola bu panele hiç uğramaz; kullanıcı merkezin sayfasında giriş yapar.
 * Kapalı kurulumda iki uç da 404 döner (bkz. MerkezGirisController).
 */
Route::get('/giris/merkez', [MerkezGirisController::class, 'baslat'])->name('kimlik.giris');
Route::get('/giris/merkez/geri', [MerkezGirisController::class, 'geri'])->name('kimlik.donus');

Route::middleware('auth')->group(function () {
    Route::get('/yetkisiz', [AuthController::class, 'yetkisiz'])->name('yetkisiz');

    Route::get('/profilim', [ProfilController::class, 'show'])->name('profil.show');
    Route::post('/profilim/ad', [ProfilController::class, 'adGuncelle'])->name('profil.ad');
    Route::post('/profilim/sifre', [ProfilController::class, 'sifreGuncelle'])->name('profil.sifre');
    Route::post('/profilim/yetki-talebi', [ProfilController::class, 'yetkiTalebiGonder'])->name('profil.yetki-talebi');

    Route::get('/', [ConversationController::class, 'index'])->name('teklif.create');
    Route::post('/teklif-olustur', [ConversationController::class, 'store'])->name('teklif.store');
    /*
     * "Devamını gör" — listenin sonraki on beşi. HTML parçası döner; satırın
     * biçimi tek yerde kalsın diye (bkz. teklif/partials/satirlar).
     */
    Route::get('/tekliflerim/daha', [ConversationController::class, 'daha'])->name('teklif.daha');

    Route::post('/tekliflerim/toplu-sil', [ConversationController::class, 'bulkDestroy'])->name('teklif.bulk-destroy');
    Route::get('/teklif/{conversation}', [ConversationController::class, 'show'])->name('teklif.show');
    Route::post('/teklif/{conversation}/mesaj', [ConversationController::class, 'reply'])->name('teklif.reply');
    Route::post('/teklif/{conversation}/sil', [ConversationController::class, 'destroy'])->name('teklif.destroy');
    Route::get('/teklif-durum/{conversation}', [ConversationController::class, 'durum'])->name('teklif.durum');

    Route::get('/bilgi-bankasi', [BilgiBankasiController::class, 'index'])->name('bilgi-bankasi.index');
    Route::post('/bilgi-ekle', [BilgiBankasiController::class, 'store'])->name('bilgi-bankasi.store');
    Route::post('/bilgi-duzenle/{entry}', [BilgiBankasiController::class, 'update'])->name('bilgi-bankasi.update');
    Route::post('/bilgi-sil/{entry}', [BilgiBankasiController::class, 'destroy'])->name('bilgi-bankasi.destroy');

    Route::get('/fiyatlar', [FiyatlarController::class, 'index'])->name('fiyatlar.index');
    Route::post('/fiyat-ekle', [FiyatlarController::class, 'store'])->name('fiyatlar.store');
    Route::post('/fiyat-duzenle/{fiyat}', [FiyatlarController::class, 'update'])->name('fiyatlar.update');
    /*
     * YERİNDE DÜZENLEME (tabloda çift tıklama). Tam formdan ayrı bir uç:
     * yalnızca bir alan gönderilir, kalanlar ekrandan geri yazılmaz.
     */
    Route::post('/fiyat-alan/{fiyat}', [FiyatlarController::class, 'alanGuncelle'])->name('fiyatlar.alan');

    Route::post('/fiyat-sil/{fiyat}', [FiyatlarController::class, 'destroy'])->name('fiyatlar.destroy');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/kullanicilar', [KullaniciController::class, 'index'])->name('kullanicilar.index');
        Route::post('/kullanicilar/ekle', [KullaniciController::class, 'store'])->name('kullanicilar.store');
        Route::post('/kullanicilar/{kullanici}/guncelle', [KullaniciController::class, 'update'])->name('kullanicilar.update');
        Route::post('/kullanicilar/{kullanici}/sil', [KullaniciController::class, 'destroy'])->name('kullanicilar.destroy');
        Route::post('/yetki-talepleri/{yetkiTalebi}/onayla', [KullaniciController::class, 'onaylaYetkiTalebi'])->name('yetki-talepleri.onayla');
        Route::post('/yetki-talepleri/{yetkiTalebi}/reddet', [KullaniciController::class, 'reddetYetkiTalebi'])->name('yetki-talepleri.reddet');

        Route::get('/istatistikler', [IstatistikController::class, 'index'])->name('istatistikler.index');

        /*
         * KAYITLAR. Kayıt tutmak tek başına yetmiyor: kimse bakamıyorsa
         * kayıt yok sayılır. Denetim kaydı "kim ne yaptı"yı, hata kaydı
         * "ne patladı"yı söyler; ikisi ayrı sorudur, ayrı ekrandır.
         */
        /*
         * AYARLAR. Panelin davranışı koddan değil buradan değişir; değer
         * veritabanında durur ve config'in üzerine biner. Sırlar burada
         * DEĞİL kurulumun ortamında (bkz. config/settings.php).
         */
        Route::get('/ayarlar', [AyarController::class, 'index'])->name('ayarlar.index');
        Route::put('/ayarlar', [AyarController::class, 'update'])->name('ayarlar.update');

        /*
         * API JETONLARI — Ayarlar'ın bir sekmesi. Dışarıdaki entegrasyonlar
         * panele bunlarla bağlanır; jeton bir kullanıcı adına çalışır ve onun
         * yetkilerini aşamaz.
         */
        Route::controller(ApiJetonController::class)
            ->prefix('api-jetonlari')
            ->name('api-jetonlari.')
            ->group(function () {
                Route::get('/', 'index')->name('index');

                /* Belge {jeton}'dan ÖNCE: yoksa "belge" bir jeton kimliği sanılırdı. */
                Route::get('belge', 'belge')->name('belge');

                Route::post('/', 'store')->name('store');
                Route::get('{jeton}', 'show')->name('show');
                Route::put('{jeton}', 'update')->name('update');
                Route::put('{jeton}/sir', 'sir')->name('sir');
                Route::put('{jeton}/iptal', 'iptal')->name('iptal');
                Route::delete('{jeton}', 'destroy')->name('destroy');
            });

        /*
         * SERTİFİKA. Tur sunucuda koşar; panel isteği bırakır ve sonucu okur
         * (bkz. SertifikaController ve sertifika-ajan.sh).
         */
        Route::get('/sertifika', [SertifikaController::class, 'index'])->name('sertifika.index');
        Route::post('/sertifika/tur', [SertifikaController::class, 'baslat'])->name('sertifika.baslat');
        Route::post('/sertifika/onayla', [SertifikaController::class, 'tamamla'])->name('sertifika.tamamla');

        /*
         * VERİTABANI. Panel bağlantıyı sınar ve taşıma isteğini bırakır;
         * kopya yeniden başlatmada, panel kapalıyken yapılır
         * (bkz. VeritabaniController ve BaglantiDosyasi).
         */
        Route::get('/veritabani', [VeritabaniController::class, 'index'])->name('veritabani.index');
        Route::post('/veritabani/sina', [VeritabaniController::class, 'sina'])->name('veritabani.sina');
        Route::post('/veritabani/tasi', [VeritabaniController::class, 'tasi'])->name('veritabani.tasi');

        /*
         * GÜNCELLEME. Yalnızca bakar: çalışan sürüm GitHub'daki son başarılı
         * derlemeyle karşılaştırılır (bkz. GuncellemeDenetimi).
         */
        Route::get('/guncelleme', [GuncellemeController::class, 'index'])->name('guncelleme.index');
        Route::post('/guncelleme', [GuncellemeController::class, 'denetle'])->name('guncelleme.denetle');

        Route::get('/kayitlar', [KayitController::class, 'denetim'])->name('kayitlar.denetim');
        Route::post('/kayitlar/dogrula', [KayitController::class, 'dogrula'])->name('kayitlar.dogrula');
        Route::get('/kayitlar/hatalar', [KayitController::class, 'hatalar'])->name('kayitlar.hatalar');
    });
});
