<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LlmService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * YEDEK SAĞLAYICI, TAM GEREKTİĞİ ANDA ÇALIŞMALI.
 *
 * Gemini kotası dolduğunda (429) devreye Groq girer. Şema istenen
 * çağrılarda — yani HER TEKLİFTE — istemde "json" sözcüğü geçmezse Groq
 * 400 döndürür:
 *
 *   'messages' must contain the word 'json' in some form,
 *   to use 'response_format' of type 'json_object'
 *
 * O zaman yedek yol tam gerektiği anda çalışmaz.
 */
class BackupProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'deneme',
            'services.gemini.model' => 'gemini-birincil',
            'services.gemini.fallback_model' => null,
            'services.groq.key' => 'groq-deneme',
            'services.groq.model' => 'deneme-model',
            'services.llm.retry_delay' => 0,
            'services.llm.retry' => 1,
        ]);
    }

    /** Şema istendiğinde Groq'a giden istemde "json" kelimesi GEÇER. */
    public function test_groq_semali_cagrida_json_kelimesini_gorur(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => ['message' => 'quota']], 429),
            '*api.groq.com*' => Http::response(
                ['choices' => [['message' => ['content' => '{"musteri_maili":"x","ic_aciklama":"y"}']]]],
                200,
            ),
        ]);

        app(LlmService::class)->generateText('talep', LlmService::systemPrompt(), [
            'type' => 'OBJECT',
            'properties' => ['musteri_maili' => ['type' => 'STRING']],
        ]);

        Http::assertSent(function (Request $istek): bool {
            if (! str_contains($istek->url(), 'groq.com')) {
                return false;
            }

            $veri = $istek->data();

            /* Groq'un koşulu: json_object kipi + mesajlarda "json" kelimesi. */
            $mesajlar = mb_strtolower(json_encode($veri['messages'] ?? [], JSON_UNESCAPED_UNICODE) ?: '');

            return ($veri['response_format']['type'] ?? null) === 'json_object'
                && str_contains($mesajlar, 'json');
        });
    }

    /** Gemini kotası dolduğunda teklif yine üretilir. */
    public function test_gemini_kotasi_dolunca_groq_devralir(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => ['message' => 'quota']], 429),
            '*api.groq.com*' => Http::response(
                ['choices' => [['message' => ['content' => 'yedekten geldi']]]],
                200,
            ),
        ]);

        $this->assertSame('yedekten geldi', app(LlmService::class)->generateText('talep'));
    }

    /** Şema istenmiyorsa istem kurcalanmaz. */
    public function test_semasiz_cagrida_istem_degismez(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response(['error' => ['message' => 'quota']], 429),
            '*api.groq.com*' => Http::response(['choices' => [['message' => ['content' => 'tamam']]]], 200),
        ]);

        app(LlmService::class)->generateText('talep', 'SİSTEM İSTEMİ');

        Http::assertSent(function (Request $istek): bool {
            if (! str_contains($istek->url(), 'groq.com')) {
                return false;
            }

            $veri = $istek->data();

            return ! isset($veri['response_format'])
                && ($veri['messages'][0]['content'] ?? null) === 'SİSTEM İSTEMİ';
        });
    }
}
