<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IdentityRefreshController;
use App\Http\Controllers\Api\KnowledgeApiController;
use App\Http\Controllers\Api\PriceApiController;
use App\Http\Controllers\Api\QuoteApiController;
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
        Route::get('prices', [PriceApiController::class, 'index'])->name('prices.index');
        Route::get('prices/{price}', [PriceApiController::class, 'show'])->name('prices.show');

        Route::get('knowledge', [KnowledgeApiController::class, 'index'])->name('knowledge.index');

        Route::post('quotes', [QuoteApiController::class, 'store'])->name('quotes.store');
        Route::get('quotes/{quote}', [QuoteApiController::class, 'show'])->name('quotes.show');
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
 * (bkz. IdentityRefreshController).
 *
 * Hız sınırı var: imza tutmasa bile bu uç merkeze bir istek attırabilir.
 */
Route::post('identity/refresh', IdentityRefreshController::class)
    ->middleware('throttle:60,1')
    ->name('api.identity.refresh');
