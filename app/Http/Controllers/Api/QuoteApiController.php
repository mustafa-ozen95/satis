<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuoteApiResource;
use App\Jobs\GenerateQuoteJob;
use App\Models\Conversation;
use App\Services\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Teklif üretimi — dışarıdan.
 *
 * METİN HEMEN DÖNMEZ: üretim LLM'e gidiyor ve dakikalar sürebiliyor; bir HTTP
 * isteğinin içinde beklenemez. Uç talebi kuyruğa alır ve konuşma kimliğini
 * döndürür, istemci durumu ayrı bir çağrıyla sorar. Ekrandaki akışın aynısı.
 *
 * KAYIT SAHİPLİDİR: konuşma, jetonun sahibine yazılır. "Kim istedi" sorusu
 * sonradan sorulacak ve jeton bir kişinin adına çalışıyor.
 */
class QuoteApiController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('can:teklif:create', only: ['store']),
            new Middleware('can:teklif:view', only: ['show']),
        ];
    }

    public function store(Request $request, ActivityRecorder $kayit): JsonResponse
    {
        $veri = $request->validate([
            'talep' => ['required', 'string', 'max:4000'],
        ]);

        $konusma = $request->user()->conversations()->create([
            'ilk_talep' => $veri['talep'],
        ]);

        $konusma->messages()->create(['role' => 'user', 'content' => $veri['talep']]);

        $kayit->log('teklif_olustur', 'API üzerinden teklif istendi', $konusma);

        GenerateQuoteJob::dispatch($konusma, $veri['talep']);

        /*
         * 202: iş KUYRUĞA ALINDI, metin hazır değil. İstemci kimliği alıp
         * durumu quotes.show ile soruyor.
         *
         * `refresh()`: durumun varsayılanı veritabanında tanımlı ve yeni
         * oluşturulan model örneğine yansımıyor; okumadan dönersek istemciye
         * boş bir durum giderdi.
         */
        return (new QuoteApiResource($konusma->refresh()))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Request $request, Conversation $quote): QuoteApiResource
    {
        /*
         * BAŞKASININ TEKLİFİ OKUNAMAZ. Yönetici hepsini görebilir; kural
         * ekrandakiyle aynı yerden geliyor (ConversationPolicy) — iki ayrı
         * kural yazılsaydı biri gevşerdi.
         */
        $this->authorize('view', $quote);

        return new QuoteApiResource(
            $quote->load(['messages' => fn ($sorgu) => $sorgu->orderBy('created_at')]),
        );
    }
}
