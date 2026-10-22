<?php

namespace App\Services;

use App\Support\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Dil modeli çağrıları — tek kapı.
 *
 * Gemini birincil, Groq yedek. Bu servis dışındaki hiçbir sınıf doğrudan
 * sağlayıcıya istek atmaz; zaman aşımı, yeniden deneme ve biçim kuralları
 * tek yerde dursun diye.
 *
 * ÜÇ KADEMELİ DAYANIKLILIK:
 *
 *   1. GEÇİCİ HATA YENİDEN DENENİR. 429 ve 5xx sağlayıcıda sıradan: model
 *      "yoğunluk" der ve saniyeler sonra aynı istek geçer. Bir Gemini
 *      modeli aynı anahtarla "UNAVAILABLE - high demand" dönerken başka bir
 *      model aynı saniyede yanıtlayabilir. Tek denemede pes etmek,
 *      kullanıcıya "hata" yazan bir teklif demek olurdu.
 *
 *   2. YEDEK MODEL. Birincil model doluysa ikinci bir Gemini modeli denenir
 *      (ayarlardan verilir). Sağlayıcı değiştirmeden önce aynı ailede
 *      kalmak, biçim ve dil davranışını korur.
 *
 *   3. YEDEK SAĞLAYICI. İkisi de olmazsa Groq.
 *
 * YAPILANDIRILMIŞ ÇIKTI: şema verildiğinde model doğrudan JSON üretir
 * (`responseMimeType: application/json`); yanıt kod bloğu işaretleri
 * olmadan geçerli JSON gelir. Metni ```json çitlerinden temizleyip parse
 * etmek yedek yoldur.
 */
class LlmService
{
    /** Sistem isteminin şirketten bağımsız kısmı; başına kimlik cümlesi gelir. */
    private const SISTEM_KURALLARI = 'Sadece sana verilen BİLGİ BANKASI ve FİYAT bağlamındaki bilgileri kullan. '
        .'Bağlamda yer almayan bir konuda kesinlikle bilgi uydurma; bunun yerine '
        ."'Bu talep için bilgi bankasında yeterli kural bulunamadı.' de. "
        .'Fiyatları asla kendin hesaplama veya tahmin etme; sadece sana verilen '
        .'fiyat kalemlerini kullan.';

    /**
     * Sistem istemi: şirketin adı kurulumdan gelir (COMPANY_NAME); verilmemişse
     * ad geçmez.
     */
    public static function systemPrompt(): string
    {
        $ad = Company::name();

        $kimlik = $ad === ''
            ? 'Sen bir satış teklif asistanısın. '
            : "Sen {$ad} için çalışan bir satış teklif asistanısın. ";

        return $kimlik.self::SISTEM_KURALLARI;
    }

    /** Sağlayıcı hata gövdesi kayda bu uzunlukta girer. */
    private const HATA_PAYI = 300;

    /**
     * @param  string|null  $system  Sistem istemi; verilmezse sistemIstemi().
     * @param  array<string, mixed>|null  $sema  JSON şeması; verilirse model
     *                                           doğrudan bu biçimde yanıtlar.
     */
    public function generateText(string $prompt, ?string $system = null, ?array $sema = null): string
    {
        $system ??= self::systemPrompt();
        $geminiKey = config('services.gemini.key');
        $sonHata = null;

        if ($geminiKey) {
            foreach ($this->geminiModels() as $model) {
                try {
                    return $this->callGemini($prompt, $system, $geminiKey, $model, $sema);
                } catch (Throwable $e) {
                    $sonHata = $e;
                    Log::warning("Gemini çağrısı başarısız ({$model}): ".$e->getMessage());
                }
            }
        }

        $groqKey = config('services.groq.key');

        if ($groqKey) {
            try {
                return $this->callGroq($prompt, $system, $groqKey, $sema);
            } catch (Throwable $e) {
                Log::error('Groq çağrısı da başarısız oldu: '.$e->getMessage());

                throw new RuntimeException('Hem Gemini hem Groq çağrısı başarısız oldu.', previous: $e);
            }
        }

        if ($sonHata !== null) {
            throw new RuntimeException('Gemini çağrısı başarısız oldu ve yedek sağlayıcı tanımlı değil.', previous: $sonHata);
        }

        throw new RuntimeException(
            'Hiçbir LLM API anahtarı tanımlı değil. Kurulumun ortamına (.env ya da platformun değişken ekranı) GEMINI_API_KEY ve/veya GROQ_API_KEY ekleyin.'
        );
    }

    /**
     * Bilgi bankası için embedding üretir. Yalnızca Gemini embedding sağlar.
     *
     * @return float[]
     */
    public function embedText(string $text): array
    {
        $geminiKey = config('services.gemini.key');

        if (! $geminiKey) {
            throw new RuntimeException('Embedding için GEMINI_API_KEY gerekli.');
        }

        $model = config('services.gemini.embed_model');

        $response = $this->http(['x-goog-api-key' => $geminiKey], (int) config('services.llm.embed_timeout', 30))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent", [
                'model' => "models/{$model}",
                'content' => ['parts' => [['text' => $text]]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini embedding çağrısı başarısız: '.$this->truncate($response->body()));
        }

        return $response->json('embedding.values', []);
    }

    /**
     * Denenecek Gemini modelleri, sırayla.
     *
     * @return list<string>
     */
    private function geminiModels(): array
    {
        return array_values(array_unique(array_filter([
            (string) config('services.gemini.model'),
            (string) config('services.gemini.fallback_model'),
        ])));
    }

    /**
     * @param  array<string, mixed>|null  $sema
     */
    protected function callGemini(string $prompt, string $system, string $key, string $model, ?array $sema = null): string
    {
        $uretim = [
            'temperature' => (float) config('services.llm.temperature', 0.2),
            'maxOutputTokens' => (int) config('services.llm.max_output_tokens', 8192),
        ];

        if ($sema !== null) {
            $uretim['responseMimeType'] = 'application/json';
            $uretim['responseSchema'] = $sema;
        }

        $response = $this->http(['x-goog-api-key' => $key])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $uretim,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Gemini çağrısı başarısız ({$response->status()}): ".$this->truncate($response->body()));
        }

        $metin = (string) $response->json('candidates.0.content.parts.0.text', '');

        /*
         * BOŞ YANIT DA HATADIR.
         *
         * Model çıktı sınırına takıldığında ya da güvenlik süzgecine
         * düştüğünde 200 dönüyor ama metin boş geliyor. Sessizce boş metin
         * döndürmek, kullanıcıya boş bir teklif göstermek olurdu; yedek
         * modele geçilsin diye hata sayılır.
         */
        if (trim($metin) === '') {
            $sebep = (string) $response->json('candidates.0.finishReason', 'bilinmiyor');

            throw new RuntimeException("Gemini boş yanıt döndürdü (bitiş sebebi: {$sebep}).");
        }

        return $metin;
    }

    /**
     * @param  array<string, mixed>|null  $sema
     */
    protected function callGroq(string $prompt, string $system, string $key, ?array $sema = null): string
    {
        $model = config('services.groq.model');

        $govde = [
            'model' => $model,
            'temperature' => (float) config('services.llm.temperature', 0.2),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        /*
         * Groq şema almıyor ama "JSON döndür" kipi var; biçim yine garanti
         * altına alınır, alan adlarını istem taşır.
         *
         * "JSON" KELİMESİ İSTEMDE GEÇMEK ZORUNDA. Groq, `json_object`
         * kipini yalnızca mesajlarda "json" geçiyorsa kabul ediyor; yoksa
         * 400 döndürüyor:
         *
         *   'messages' must contain the word 'json' in some form,
         *   to use 'response_format' of type 'json_object'
         *
         * Bu kural atlanırsa Gemini kotası dolduğunda yedek sağlayıcı da
         * düşer — yedek, TAM GEREKTİĞİ ANDA çalışmaz.
         */
        if ($sema !== null) {
            $govde['response_format'] = ['type' => 'json_object'];

            $govde['messages'][0]['content'] = $system
                ."\n\nYanıtı YALNIZCA geçerli bir JSON nesnesi olarak ver; başka hiçbir metin ekleme.";
        }

        $response = $this->http([], null, $key)
            ->post('https://api.groq.com/openai/v1/chat/completions', $govde);

        if (! $response->successful()) {
            throw new RuntimeException("Groq çağrısı başarısız ({$response->status()}): ".$this->truncate($response->body()));
        }

        $metin = (string) $response->json('choices.0.message.content', '');

        if (trim($metin) === '') {
            throw new RuntimeException('Groq boş yanıt döndürdü.');
        }

        return $metin;
    }

    /**
     * Ortak istek kalıbı: zaman aşımı + geçici hatada yeniden deneme.
     *
     * ZAMAN AŞIMI ŞART: Laravel'in varsayılanı 30 saniye ve uzun bir teklif
     * metni bunu aşabilir; aşınca istek "başarısız" sayılıp yedek modele
     * düşer ve iş iki katına çıkar. Sınır ayardan verilir.
     *
     * @param  array<string, string>  $basliklar
     */
    private function http(array $basliklar = [], ?int $zamanAsimi = null, ?string $jeton = null): PendingRequest
    {
        $istek = Http::timeout($zamanAsimi ?? (int) config('services.llm.timeout', 120))
            ->connectTimeout((int) config('services.llm.connect_timeout', 10))
            ->retry(
                (int) config('services.llm.retry', 3),
                (int) config('services.llm.retry_delay', 1500),
                fn (Throwable $e): bool => $this->isTransient($e),
                throw: false,
            );

        if ($basliklar !== []) {
            $istek = $istek->withHeaders($basliklar);
        }

        return $jeton !== null ? $istek->withToken($jeton) : $istek;
    }

    /**
     * Bu hata YENİDEN DENENMELİ mi?
     *
     * Kota (429) ve sağlayıcı arızası (5xx) geçicidir: saniyeler sonra aynı
     * istek geçer. 400 ve 403 ise isteğin kendisiyle ilgilidir; tekrar
     * denemek yalnızca gecikme üretir.
     */
    private function isTransient(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if (! $e instanceof RequestException) {
            return false;
        }

        return in_array($e->response->status(), [408, 409, 425, 429, 500, 502, 503, 504], true);
    }

    /**
     * Sağlayıcı hata gövdesi KISALTILIR.
     *
     * Gövde tamamıyla kayda ve oradan ekrana düşüyordu: kilobaytlarca JSON,
     * içinde isteğin bir kısmı. Hata satırı "ne oldu" sorusunu yanıtlamalı,
     * yanıtın tamamını taşımamalı.
     */
    private function truncate(string $govde): string
    {
        $govde = trim(preg_replace('/\s+/', ' ', $govde) ?? $govde);

        return mb_strlen($govde) > self::HATA_PAYI
            ? mb_substr($govde, 0, self::HATA_PAYI).'…'
            : $govde;
    }
}
