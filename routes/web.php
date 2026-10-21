<?php

use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StatisticController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\CenterLoginController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\PricesController;
use App\Http\Controllers\ProfileController;
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
 * Kapalı kurulumda iki uç da 404 döner (bkz. CenterLoginController).
 */
Route::get('/giris/merkez', [CenterLoginController::class, 'baslat'])->name('kimlik.giris');
Route::get('/giris/merkez/geri', [CenterLoginController::class, 'geri'])->name('kimlik.donus');

Route::middleware('auth')->group(function () {
    Route::get('/yetkisiz', [AuthController::class, 'yetkisiz'])->name('yetkisiz');

    Route::get('/profilim', [ProfileController::class, 'show'])->name('profil.show');
    Route::post('/profilim/ad', [ProfileController::class, 'adGuncelle'])->name('profil.ad');
    Route::post('/profilim/sifre', [ProfileController::class, 'sifreGuncelle'])->name('profil.sifre');
    Route::post('/profilim/yetki-talebi', [ProfileController::class, 'yetkiTalebiGonder'])->name('profil.yetki-talebi');

    Route::get('/', [ConversationController::class, 'index'])->name('teklif.create');
    Route::post('/teklif-olustur', [ConversationController::class, 'store'])->name('teklif.store');
    /*
     * "Devamını gör" — listenin sonraki on beşi. HTML parçası döner; satırın
     * biçimi tek yerde kalsın diye (bkz. quote/partials/satirlar).
     */
    Route::get('/tekliflerim/daha', [ConversationController::class, 'daha'])->name('teklif.daha');

    Route::post('/tekliflerim/toplu-sil', [ConversationController::class, 'bulkDestroy'])->name('teklif.bulk-destroy');
    Route::get('/teklif/{conversation}', [ConversationController::class, 'show'])->name('teklif.show');
    Route::post('/teklif/{conversation}/mesaj', [ConversationController::class, 'reply'])->name('teklif.reply');
    Route::post('/teklif/{conversation}/sil', [ConversationController::class, 'destroy'])->name('teklif.destroy');
    Route::get('/teklif-durum/{conversation}', [ConversationController::class, 'durum'])->name('teklif.durum');

    Route::get('/bilgi-bankasi', [KnowledgeBaseController::class, 'index'])->name('bilgi-bankasi.index');
    Route::post('/bilgi-ekle', [KnowledgeBaseController::class, 'store'])->name('bilgi-bankasi.store');
    Route::post('/bilgi-duzenle/{entry}', [KnowledgeBaseController::class, 'update'])->name('bilgi-bankasi.update');
    Route::post('/bilgi-sil/{entry}', [KnowledgeBaseController::class, 'destroy'])->name('bilgi-bankasi.destroy');

    Route::get('/fiyatlar', [PricesController::class, 'index'])->name('fiyatlar.index');
    Route::post('/fiyat-ekle', [PricesController::class, 'store'])->name('fiyatlar.store');
    Route::post('/fiyat-duzenle/{fiyat}', [PricesController::class, 'update'])->name('fiyatlar.update');
    /*
     * YERİNDE DÜZENLEME (tabloda çift tıklama). Tam formdan ayrı bir uç:
     * yalnızca bir alan gönderilir, kalanlar ekrandan geri yazılmaz.
     */
    Route::post('/fiyat-alan/{fiyat}', [PricesController::class, 'alanGuncelle'])->name('fiyatlar.alan');

    Route::post('/fiyat-sil/{fiyat}', [PricesController::class, 'destroy'])->name('fiyatlar.destroy');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/kullanicilar', [UserController::class, 'index'])->name('kullanicilar.index');
        Route::post('/kullanicilar/ekle', [UserController::class, 'store'])->name('kullanicilar.store');
        Route::post('/kullanicilar/{kullanici}/guncelle', [UserController::class, 'update'])->name('kullanicilar.update');
        Route::post('/kullanicilar/{kullanici}/sil', [UserController::class, 'destroy'])->name('kullanicilar.destroy');
        Route::post('/yetki-talepleri/{yetkiTalebi}/onayla', [UserController::class, 'onaylaYetkiTalebi'])->name('yetki-talepleri.onayla');
        Route::post('/yetki-talepleri/{yetkiTalebi}/reddet', [UserController::class, 'reddetYetkiTalebi'])->name('yetki-talepleri.reddet');

        Route::get('/istatistikler', [StatisticController::class, 'index'])->name('istatistikler.index');

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
        Route::get('/ayarlar', [SettingController::class, 'index'])->name('ayarlar.index');
        Route::put('/ayarlar', [SettingController::class, 'update'])->name('ayarlar.update');

        /*
         * API JETONLARI — Ayarlar'ın bir sekmesi. Dışarıdaki entegrasyonlar
         * panele bunlarla bağlanır; jeton bir kullanıcı adına çalışır ve onun
         * yetkilerini aşamaz.
         */
        Route::controller(ApiTokenController::class)
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
         * (bkz. CertificateController ve certificate-agent.sh).
         */
        Route::get('/sertifika', [CertificateController::class, 'index'])->name('sertifika.index');
        Route::post('/sertifika/tur', [CertificateController::class, 'baslat'])->name('sertifika.baslat');
        Route::post('/sertifika/onayla', [CertificateController::class, 'tamamla'])->name('sertifika.tamamla');

        /*
         * VERİTABANI. Panel bağlantıyı sınar ve taşıma isteğini bırakır;
         * kopya yeniden başlatmada, panel kapalıyken yapılır
         * (bkz. DatabaseController ve ConnectionFile).
         */
        Route::get('/veritabani', [DatabaseController::class, 'index'])->name('veritabani.index');
        Route::post('/veritabani/sina', [DatabaseController::class, 'sina'])->name('veritabani.sina');
        Route::post('/veritabani/tasi', [DatabaseController::class, 'tasi'])->name('veritabani.tasi');

        /*
         * GÜNCELLEME. Yalnızca bakar: çalışan sürüm GitHub'daki son başarılı
         * derlemeyle karşılaştırılır (bkz. UpdateCheck).
         */
        Route::get('/guncelleme', [UpdateController::class, 'index'])->name('guncelleme.index');
        Route::post('/guncelleme', [UpdateController::class, 'denetle'])->name('guncelleme.denetle');

        Route::get('/kayitlar', [LogController::class, 'denetim'])->name('kayitlar.denetim');
        Route::post('/kayitlar/dogrula', [LogController::class, 'dogrula'])->name('kayitlar.dogrula');
        Route::get('/kayitlar/hatalar', [LogController::class, 'hatalar'])->name('kayitlar.hatalar');
    });
});
