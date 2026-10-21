<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LlmService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Dil modeli çağrısının DAYANIKLILIĞI.
 *
 * Teklif üretimi tek bir dış çağrıya bağlı ve o çağrı sahada üç biçimde
 * düşüyor: kota (429), sağlayıcı yoğunluğu (503) ve boş yanıt. Üçünde de
 * kullanıcıya "hata" yazmak yerine denenecek bir adım daha var.
 */
class LlmDayanikliligiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'deneme-anahtar',
            'services.gemini.model' => 'gemini-birincil',
            'services.gemini.yedek_model' => 'gemini-yedek',
            'services.groq.key' => null,

            /* Sınamada beklemeyelim. */
            'services.llm.retry_delay' => 0,
        ]);
    }

    public function test_gecici_hatada_yeniden_denenir(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::sequence()
                ->push(['error' => ['message' => 'yoğunluk']], 503)
                ->push(['candidates' => [['content' => ['parts' => [['text' => '{"a":1}']]]]]], 200),
        ]);

        $this->assertSame('{"a":1}', app(LlmService::class)->generateText('talep'));
    }

    /**
     * Birincil model doluysa YEDEK MODEL denenir — sağlayıcı değiştirmeden.
     */
    public function test_birincil_model_dolunca_yedek_modele_gecilir(): void
    {
        config(['services.llm.retry' => 1]);

        Http::fake([
            '*models/gemini-birincil*' => Http::response(['error' => ['message' => 'high demand']], 503),
            '*models/gemini-yedek*' => Http::response(
                ['candidates' => [['content' => ['parts' => [['text' => 'yedekten geldi']]]]]],
                200,
            ),
        ]);

        $this->assertSame('yedekten geldi', app(LlmService::class)->generateText('talep'));
    }

    /**
     * BOŞ YANIT DA HATADIR: 200 dönüyor ama metin yok (çıktı sınırı ya da
     * güvenlik süzgeci). Sessizce boş teklif göstermek yerine yedeğe geçilir.
     */
    public function test_bos_yanit_hata_sayilir(): void
    {
        config(['services.llm.retry' => 1]);

        Http::fake([
            '*models/gemini-birincil*' => Http::response(
                ['candidates' => [['content' => ['parts' => [['text' => '']]], 'finishReason' => 'MAX_TOKENS']]],
                200,
            ),
            '*models/gemini-yedek*' => Http::response(
                ['candidates' => [['content' => ['parts' => [['text' => 'dolu yanıt']]]]]],
                200,
            ),
        ]);

        $this->assertSame('dolu yanıt', app(LlmService::class)->generateText('talep'));
    }

    /**
     * Kalıcı hata (400) YENİDEN DENENMEZ: istek yanlış, tekrar etmek yalnızca
     * gecikme üretir.
     */
    public function test_kalici_hata_yeniden_denenmez(): void
    {
        config(['services.gemini.yedek_model' => null]);

        $sayac = 0;

        Http::fake(function () use (&$sayac) {
            $sayac++;

            return Http::response(['error' => ['message' => 'geçersiz istek']], 400);
        });

        $this->expectException(RuntimeException::class);

        try {
            app(LlmService::class)->generateText('talep');
        } finally {
            $this->assertSame(1, $sayac, 'Kalıcı hata bir kez denenmeliydi.');
        }
    }

    /** Şema verildiğinde sağlayıcıya JSON kipi bildirilir. */
    public function test_sema_verilince_json_kipi_istenir(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{}']]]]]], 200)]);

        app(LlmService::class)->generateText('talep', LlmService::sistemIstemi(), [
            'type' => 'OBJECT',
            'properties' => ['musteri_maili' => ['type' => 'STRING']],
        ]);

        Http::assertSent(function (Request $istek): bool {
            $veri = $istek->data();

            return ($veri['generationConfig']['responseMimeType'] ?? null) === 'application/json'
                && isset($veri['generationConfig']['responseSchema']);
        });
    }

    /**
     * Hata gövdesi KISALIR: sağlayıcının kilobaytlarca yanıtı kayda ve oradan
     * ekrana düşüyordu.
     */
    public function test_saglayici_hata_govdesi_kisaltilir(): void
    {
        config(['services.gemini.yedek_model' => null, 'services.llm.retry' => 1]);

        Http::fake(['*' => Http::response(['error' => ['message' => str_repeat('x', 5000)]], 400)]);

        try {
            app(LlmService::class)->generateText('talep');
            $this->fail('Hata bekleniyordu.');
        } catch (RuntimeException $e) {
            $this->assertLessThan(500, mb_strlen($e->getMessage()));
        }
    }
}
