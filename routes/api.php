<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BilgiApiController;
use App\Http\Controllers\Api\FiyatApiController;
use App\Http\Controllers\Api\KimlikTazeleController;
use App\Http\Controllers\Api\TeklifApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API rotaları
|--------------------------------------------------------------------------
|
| AYRI BİR KAPI: oturum çerezi taşımaz, CSRF beklemez. Şu an tek bir uç var
| ve kimliğini imzadan alıyor.
|
*/

/*
|--------------------------------------------------------------------------
| v1
|--------------------------------------------------------------------------
|
| Kimlik "Authorization: Bearer <jeton>" başlığındadır; oturum çerezi kabul
| edilmez (bkz. ApiTokenGuard). Jeton SAHİBİNİ AŞAMAZ: yetkisi, jetonun kendi
| listesi ile sahibinin panel yetkilerinin kesişimidir.
|
| Sürüm öneki BAŞTAN var: bir gün bir alanın adı değiştiğinde, dışarıdaki
| entegrasyonu kırmadan ikinci bir sürüm açabilmenin tek yolu bu.
|
*/
Route::middleware(['auth:api', 'throttle:api'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('fiyatlar', [FiyatApiController::class, 'index'])->name('fiyatlar.index');
        Route::get('fiyatlar/{fiyat}', [FiyatApiController::class, 'show'])->name('fiyatlar.show');

        Route::get('bilgi', [BilgiApiController::class, 'index'])->name('bilgi.index');

        Route::post('teklif', [TeklifApiController::class, 'store'])->name('teklif.store');
        Route::get('teklif/{teklif}', [TeklifApiController::class, 'show'])->name('teklif.show');
    });

/*
 * MERKEZDEN GELEN "bu kimlik değişti" bildirimi.
 *
 * Merkez rol ya da durum değişince ANINDA haber veriyor; gecelik çekme turu
 * yedekte kalıyor (bildirim kaybolabilir: ağ, yeniden başlatma, panel o an
 * kapalı).
 *
 * Jeton gerektirmez: kimlik doğrulaması istek gövdesinin HMAC imzasıdır.
 * Gelen veriye GÜVENİLMEZ; panel doğruyu merkezden kendi sorar
 * (bkz. KimlikTazeleController).
 *
 * Hız sınırı var: imza tutmasa bile bu uç merkeze bir istek attırabilir.
 */
Route::post('kimlik/tazele', KimlikTazeleController::class)
    ->middleware('throttle:60,1')
    ->name('api.kimlik.tazele');
