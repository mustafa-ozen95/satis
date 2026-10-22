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
Route::get('/login/center', [CenterLoginController::class, 'start'])->name('identity.login');
Route::get('/login/center/callback', [CenterLoginController::class, 'callback'])->name('identity.callback');

Route::middleware('auth')->group(function () {
    Route::get('/unauthorized', [AuthController::class, 'unauthorized'])->name('unauthorized');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/name', [ProfileController::class, 'updateName'])->name('profile.name');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/permission-request', [ProfileController::class, 'submitPermissionRequest'])->name('profile.permission-request');

    Route::get('/', [ConversationController::class, 'index'])->name('quote.create');
    Route::post('/quote-create', [ConversationController::class, 'store'])->name('quote.store');
    /*
     * "Devamını gör" — listenin sonraki on beşi. HTML parçası döner; satırın
     * biçimi tek yerde kalsın diye (bkz. quote/partials/satirlar).
     */
    Route::get('/quotes/more', [ConversationController::class, 'more'])->name('quote.more');

    Route::post('/quotes/bulk-delete', [ConversationController::class, 'bulkDestroy'])->name('quote.bulk-destroy');
    Route::get('/quote/{conversation}', [ConversationController::class, 'show'])->name('quote.show');
    Route::post('/quote/{conversation}/message', [ConversationController::class, 'reply'])->name('quote.reply');
    Route::post('/quote/{conversation}/delete', [ConversationController::class, 'destroy'])->name('quote.destroy');
    Route::get('/quote-status/{conversation}', [ConversationController::class, 'status'])->name('quote.status');

    Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');
    Route::post('/knowledge-add', [KnowledgeBaseController::class, 'store'])->name('knowledge-base.store');
    Route::post('/knowledge-edit/{entry}', [KnowledgeBaseController::class, 'update'])->name('knowledge-base.update');
    Route::post('/knowledge-delete/{entry}', [KnowledgeBaseController::class, 'destroy'])->name('knowledge-base.destroy');

    Route::get('/prices', [PricesController::class, 'index'])->name('prices.index');
    Route::post('/price-add', [PricesController::class, 'store'])->name('prices.store');
    Route::post('/price-edit/{price}', [PricesController::class, 'update'])->name('prices.update');
    /*
     * YERİNDE DÜZENLEME (tabloda çift tıklama). Tam formdan ayrı bir uç:
     * yalnızca bir alan gönderilir, kalanlar ekrandan geri yazılmaz.
     */
    Route::post('/price-field/{price}', [PricesController::class, 'updateField'])->name('prices.field');

    Route::post('/price-delete/{price}', [PricesController::class, 'destroy'])->name('prices.destroy');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/add', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/update', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/delete', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/permission-requests/{permissionRequest}/approve', [UserController::class, 'approvePermissionRequest'])->name('permission-requests.approve');
        Route::post('/permission-requests/{permissionRequest}/reject', [UserController::class, 'rejectPermissionRequest'])->name('permission-requests.reject');

        Route::get('/statistics', [StatisticController::class, 'index'])->name('statistics.index');

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
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        /*
         * API JETONLARI — Ayarlar'ın bir sekmesi. Dışarıdaki entegrasyonlar
         * panele bunlarla bağlanır; jeton bir kullanıcı adına çalışır ve onun
         * yetkilerini aşamaz.
         */
        Route::controller(ApiTokenController::class)
            ->prefix('api-tokens')
            ->name('api-tokens.')
            ->group(function () {
                Route::get('/', 'index')->name('index');

                /* Docs {token}'dan ÖNCE: yoksa "docs" bir jeton kimliği sanılırdı. */
                Route::get('docs', 'docs')->name('docs');

                Route::post('/', 'store')->name('store');
                Route::get('{token}', 'show')->name('show');
                Route::put('{token}', 'update')->name('update');
                Route::put('{token}/secret', 'secret')->name('secret');
                Route::put('{token}/revoke', 'revoke')->name('revoke');
                Route::delete('{token}', 'destroy')->name('destroy');
            });

        /*
         * SERTİFİKA. Tur sunucuda koşar; panel isteği bırakır ve sonucu okur
         * (bkz. CertificateController ve certificate-agent.sh).
         */
        Route::get('/certificate', [CertificateController::class, 'index'])->name('certificate.index');
        Route::post('/certificate/run', [CertificateController::class, 'start'])->name('certificate.start');
        Route::post('/certificate/confirm', [CertificateController::class, 'complete'])->name('certificate.complete');

        /*
         * VERİTABANI. Panel bağlantıyı sınar ve taşıma isteğini bırakır;
         * kopya yeniden başlatmada, panel kapalıyken yapılır
         * (bkz. DatabaseController ve ConnectionFile).
         */
        Route::get('/database', [DatabaseController::class, 'index'])->name('database.index');
        Route::post('/database/test', [DatabaseController::class, 'test'])->name('database.test');
        Route::post('/database/migrate', [DatabaseController::class, 'migrate'])->name('database.migrate');

        /*
         * GÜNCELLEME. Yalnızca bakar: çalışan sürüm GitHub'daki son başarılı
         * derlemeyle karşılaştırılır (bkz. UpdateCheck).
         */
        Route::get('/update', [UpdateController::class, 'index'])->name('update.index');
        Route::post('/update', [UpdateController::class, 'check'])->name('update.check');

        Route::get('/logs', [LogController::class, 'audit'])->name('logs.audit');
        Route::post('/logs/verify', [LogController::class, 'verify'])->name('logs.verify');
        Route::get('/logs/errors', [LogController::class, 'errors'])->name('logs.errors');
    });
});
