<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KnowledgeRecord;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Bilgi bankası: kurum bilgisinin deposu ve anlamsal araması.
 *
 * ASIL VERİ VERİTABANINDA (bilgi_kayitlari). Chroma ondan TÜRETİLEN vektör
 * indeksidir: kayıtlar panelin yedeğine girer, Chroma düşünce ekran ayakta
 * kalır, düz metin araması mümkün olur.
 *
 * Resmî bir PHP ChromaDB SDK'si yok; REST API'sine doğrudan gidiliyor.
 * Chroma 1.x yalnızca /api/v2 (tenant/database kapsamlı) API'sini destekler.
 *
 * DIŞ SERVİSE HER ÇAĞRIDA ZAMAN AŞIMI VAR: yanıt vermeyen bir Chroma,
 * kuyruğun tek işçisini varsayılan süre boyunca bloke etmesin.
 */
class KnowledgeBaseService
{
    /*
     * KOLEKSİYON ADI DEĞİŞTİRİLEMEZ: var olan kayıtlar bu adın altında
     * durur. Ad değişirse uygulama sessizce BOŞ bir koleksiyon yaratıp onu
     * kullanır ve bilgi bankası boş görünür.
     */
    private const COLLECTION_NAME = 'bilgi_bankasi';

    /** Chroma'ya tanınan süre (saniye). Arama bir istek içinde bekleniyor. */
    private const ZAMAN_ASIMI = 10;

    /** Koleksiyon kimliği önbellek anahtarı. */
    private const KOLEKSIYON_ANAHTARI = 'bilgi:chroma-koleksiyon';

    /** Chroma'dan tek istekte okunan ya da silinen belge sayısı. */
    private const SAYFA = 200;

    private string $baseUrl;

    public function __construct(protected LlmService $llm)
    {
        $chromaUrl = rtrim((string) config('services.chroma.url', 'http://localhost:8001'), '/');
        $tenant = config('services.chroma.tenant', 'default_tenant');
        $database = config('services.chroma.database', 'default_database');

        $this->baseUrl = "{$chromaUrl}/api/v2/tenants/{$tenant}/databases/{$database}";
    }

    /**
     * Soruya en alakalı bilgi parçaları.
     *
     * CHROMA DÜŞERSE TEKLİF ÜRETİMİ DURMAZ: anlamsal arama yapılamıyorsa
     * veritabanında düz metin araması yapılır. Anlamsal değil, ama hiç bağlam
     * olmamasından iyidir ve kullanıcı "üretilemedi" yerine bir metin alır.
     *
     * @return array<int, array{text: string, metadata: array, distance: ?float}>
     */
    public function query(string $question, int $nResults = 5): array
    {
        try {
            return $this->chromaSorgusu($question, $nResults);
        } catch (Throwable $e) {
            report($e);
            Log::warning('Bilgi bankası anlamsal araması yapılamadı; metin aramasına düşülüyor.', [
                'sebep' => $e->getMessage(),
            ]);

            return $this->metinAramasi($question, $nResults);
        }
    }

    /** Bilgi bankasındaki bütün kayıtlar — VERİTABANINDAN, dış servise sormadan. */
    public function listEntries(int $limit = 300): array
    {
        return KnowledgeRecord::query()
            ->orderBy('kategori')
            ->orderBy('baslik')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgeRecord $k): array => [
                'id' => $k->id,
                'text' => $k->metin,
                'title' => $k->baslik,
                'category' => $k->kategori,
                'source' => $k->kaynakEtiketi(),
                'indekslendi' => $k->indekslendi !== null,
            ])
            ->all();
    }

    /**
     * Yeni kayıt.
     *
     * ÖNCE VERİTABANI, sonra indeks. Ters sırada olsaydı Chroma'ya yazılıp
     * veritabanına yazılamayan bir kayıt "var ama görünmez" olurdu.
     * İndeksleme başarısızsa kayıt yine durur; `bilgi:indeksle` onu yakalar.
     */
    public function addEntry(string $text, string $title, string $category, string $source = 'manuel'): int
    {
        $kayit = KnowledgeRecord::create([
            'baslik' => $title,
            'kategori' => $category,
            'metin' => $text,
            'kaynak' => $source,
            'ekleyen_id' => Auth::id(),
        ]);

        $this->indeksle($kayit);

        return $kayit->id;
    }

    public function updateEntry(int|string $entryId, string $text, string $title, string $category): void
    {
        $kayit = KnowledgeRecord::findOrFail($entryId);

        $kayit->update(['metin' => $text, 'baslik' => $title, 'kategori' => $category]);

        $this->indeksle($kayit);
    }

    public function deleteEntry(int|string $entryId): void
    {
        $kayit = KnowledgeRecord::findOrFail($entryId);

        /*
         * İndeksten SİLMEYE ÇALIŞ, olmazsa devam et: asıl kayıt gitmeli.
         * Artakalan bir vektör yanlış bağlam üretebilir ama `bilgi:indeksle`
         * onu temizler; silinmeyen bir kayıt ise kullanıcının gördüğü hatadır.
         */
        if ($kayit->chroma_id !== null) {
            try {
                $this->istek()->post("{$this->baseUrl}/collections/{$this->koleksiyon()}/delete", [
                    'ids' => [$kayit->chroma_id],
                ])->throw();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $kayit->delete();
    }

    /**
     * Bir kaydı Chroma'ya yazar (varsa üzerine).
     *
     * Embedding ÜCRETLİ ve yavaş bir çağrı; metin değişmediyse yeniden
     * üretilmez (bkz. gomme).
     */
    public function indeksle(KnowledgeRecord $kayit): bool
    {
        try {
            $chromaId = $kayit->chroma_id ?? (string) Str::uuid();

            $this->istek()->post("{$this->baseUrl}/collections/{$this->koleksiyon()}/upsert", [
                'ids' => [$chromaId],
                'embeddings' => [$this->gomme($kayit->metin)],
                'documents' => [$kayit->metin],
                'metadatas' => [[
                    'title' => $kayit->baslik,
                    'category' => $kayit->kategori,
                    /* Üstveride HAM değer: Chroma'ya yazılan ekran etiketi değil. */
                    'source' => $kayit->kaynak,
                    'kayit_id' => (string) $kayit->id,
                ]],
            ])->throw();

            $kayit->forceFill(['chroma_id' => $chromaId, 'indekslendi' => now()])->save();

            return true;
        } catch (Throwable $e) {
            report($e);
            Log::warning('Bilgi kaydı indekslenemedi; kayıt veritabanında duruyor.', [
                'kayit' => $kayit->id,
                'sebep' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * METNİN GÖMMESİ (embedding) — aynı metin iki kez üretilmez.
     *
     * Çağrı hem ücretli hem yavaş; kayıt güncellenirken başlık değişip metin
     * aynı kaldığında yeniden üretmek boşuna. Anahtar metnin özetidir, metnin
     * kendisi değil: uzun metinler önbellek anahtarına sığmaz.
     *
     * @return list<float>
     */
    private function gomme(string $metin): array
    {
        /*
         * ANAHTAR MODELİ DE TAŞIR.
         *
         * Gömme modeli değiştiğinde vektör uzayı da değişir; eski modelin
         * ürettiği vektörü yeni modelin sorgusuyla karşılaştırmak anlamsız
         * sonuç verir. Anahtar yalnızca metne baksaydı model değişse bile
         * önbellek eski vektörü döndürürdü — sessiz ve teşhisi zor.
         */
        $anahtar = 'bilgi:gomme:'.config('services.gemini.embed_model').':'.hash('sha256', $metin);

        try {
            return Cache::remember($anahtar, now()->addDays(30), fn (): array => $this->llm->embedText($metin));
        } catch (Throwable $e) {
            /* Önbellek erişilemezse çağrı yine yapılır; yalnızca ucuzluk kaybolur. */
            report($e);

            return $this->llm->embedText($metin);
        }
    }

    /** @return array<int, array{text: string, metadata: array, distance: ?float}> */
    private function chromaSorgusu(string $question, int $nResults): array
    {
        $koleksiyon = $this->koleksiyon();
        $adet = $this->count($koleksiyon);

        if ($adet === 0) {
            return [];
        }

        $yanit = $this->istek()->post("{$this->baseUrl}/collections/{$koleksiyon}/query", [
            'query_embeddings' => [$this->gomme($question)],
            'n_results' => min($nResults, $adet),
        ])->throw();

        $belgeler = $yanit->json('documents.0', []);
        $veriler = $yanit->json('metadatas.0', []);
        $uzakliklar = $yanit->json('distances.0', []);

        $cikti = [];

        foreach ($belgeler as $i => $belge) {
            $cikti[] = [
                'text' => $belge,
                'metadata' => $veriler[$i] ?? [],
                'distance' => $uzakliklar[$i] ?? null,
            ];
        }

        return $cikti;
    }

    /**
     * Anlamsal arama yapılamadığında düz metin araması.
     *
     * Sorunun kelimelerini tek tek arar; kısa kelimeler (ve, ile, bir) elenir,
     * yoksa her kayıt eşleşirdi.
     *
     * @return array<int, array{text: string, metadata: array, distance: ?float}>
     */
    private function metinAramasi(string $question, int $nResults): array
    {
        $kelimeler = collect(preg_split('/\W+/u', mb_strtolower($question)) ?: [])
            ->filter(fn (string $k): bool => mb_strlen($k) >= 4)
            ->take(6);

        if ($kelimeler->isEmpty()) {
            return [];
        }

        return KnowledgeRecord::query()
            ->where(function ($sorgu) use ($kelimeler): void {
                foreach ($kelimeler as $kelime) {
                    $sorgu->orWhere(fn ($i) => $i->metinde($kelime));
                }
            })
            ->limit($nResults)
            ->get()
            ->map(fn (KnowledgeRecord $k): array => [
                'text' => $k->metin,
                'metadata' => ['title' => $k->baslik, 'category' => $k->kategori, 'source' => $k->kaynak],
                'distance' => null,
            ])
            ->all();
    }

    private function istek(): PendingRequest
    {
        return Http::timeout(self::ZAMAN_ASIMI)->acceptJson();
    }

    private function count(string $koleksiyon): int
    {
        return (int) $this->istek()->get("{$this->baseUrl}/collections/{$koleksiyon}/count")->throw()->json();
    }

    /**
     * Koleksiyon kimliği.
     *
     * ÖNBELLEKTE DURUR: yoksa her istekte iki ek HTTP çağrısı (sor, yoksa
     * yarat) gerekir ve kimlik neredeyse hiç değişmez. Koleksiyon silinirse
     * önbellek de temizlenmeli (bkz. bilgi:indeksle --sifirla).
     */
    private function koleksiyon(): string
    {
        try {
            return Cache::rememberForever(self::KOLEKSIYON_ANAHTARI, fn (): string => $this->koleksiyonuBul());
        } catch (Throwable) {
            return $this->koleksiyonuBul();
        }
    }

    private function koleksiyonuBul(): string
    {
        $yanit = $this->istek()->get("{$this->baseUrl}/collections/".self::COLLECTION_NAME);

        if ($yanit->successful() && filled($yanit->json('id'))) {
            return (string) $yanit->json('id');
        }

        $yaratma = $this->istek()->post("{$this->baseUrl}/collections", [
            'name' => self::COLLECTION_NAME,
            'get_or_create' => true,
        ]);

        if (! $yaratma->successful() || blank($yaratma->json('id'))) {
            throw new RuntimeException('Chroma koleksiyonu oluşturulamadı: '.$yaratma->body());
        }

        return (string) $yaratma->json('id');
    }

    /**
     * İndeksteki BÜTÜN belgeler — yetim aramak için (bkz. bilgi:indeksle).
     *
     * Sayfa sayfa okunur: tek istek koleksiyon büyüdükçe hem yanıtı hem
     * zaman aşımını şişirirdi. Okunamazsa İSTİSNA atar; yarım bir liste
     * "bunlar hiçbir kayda ait değil" kararına temel olamaz.
     *
     * Metin istenmezse Chroma `documents` alanını hiç göndermez (null).
     *
     * @return list<array{id: string, metin: ?string, ustveri: array<string, mixed>}>
     */
    public function vektorler(bool $metinle = false): array
    {
        $koleksiyon = $this->koleksiyon();
        $belgeler = [];
        $gorulen = [];

        for ($atla = 0; ; $atla += self::SAYFA) {
            $yanit = $this->istek()->post("{$this->baseUrl}/collections/{$koleksiyon}/get", [
                'include' => $metinle ? ['metadatas', 'documents'] : ['metadatas'],
                'limit' => self::SAYFA,
                'offset' => $atla,
            ])->throw();

            $kimlikler = $yanit->json('ids') ?? [];

            /* Sayfalamayı yok sayan bir sunucu aynı sayfayı sonsuza dek verirdi. */
            if ($kimlikler !== [] && isset($gorulen[$kimlikler[0]])) {
                throw new RuntimeException('Chroma sayfalamayı yok sayıyor: aynı belgeler yeniden geldi.');
            }

            foreach ($kimlikler as $i => $kimlik) {
                $gorulen[$kimlik] = true;
                $belgeler[] = [
                    'id' => (string) $kimlik,
                    'metin' => $yanit->json("documents.{$i}"),
                    /* Üstverisiz belge için Chroma null döndürüyor. */
                    'ustveri' => (array) ($yanit->json("metadatas.{$i}") ?? []),
                ];
            }

            if (count($kimlikler) < self::SAYFA) {
                return $belgeler;
            }
        }
    }

    /**
     * Belgeleri indeksten siler — YALNIZCA yetim temizliği için.
     *
     * Bir kaydı silmenin yolu deleteEntry'dir: o, kaydı da siler. Olmayan bir
     * kimlik Chroma'da hata değildir; iki tur aynı yetimi silebilir.
     *
     * @param  list<string>  $kimlikler
     */
    public function vektorleriSil(array $kimlikler): void
    {
        foreach (array_chunk($kimlikler, self::SAYFA) as $parca) {
            $this->istek()->post("{$this->baseUrl}/collections/{$this->koleksiyon()}/delete", [
                'ids' => $parca,
            ])->throw();
        }
    }

    /** Önbellekteki koleksiyon kimliğini unutur. */
    public function koleksiyonuUnut(): void
    {
        Cache::forget(self::KOLEKSIYON_ANAHTARI);
    }

    /**
     * İNDEKSİ SIFIRLAR: koleksiyonu siler, kimliğini unutur.
     *
     * Gömme modeli değiştiğinde şart. Eski vektörler başka bir uzayda ve
     * yeni sorgularla karşılaştırıldığında rastgele sonuç verir; üstelik
     * silinmiş kayıtlardan kalan öksüz vektörler de aynı koleksiyonda durur.
     *
     * VERİ KAYBI YOK: asıl kayıtlar veritabanında, Chroma ondan türetilen
     * indekstir (bkz. bilgi:indeksle).
     *
     * SİLİNEMEZSE SIFIRLANMAZ, istisna atar. Hata yutulup kimlikler yine de
     * boşaltılsaydı koleksiyon yerinde kalır, her kayıt YENİ bir kimlikle
     * ikinci kez yazılır, eskiler yetim kalırdı.
     * Koleksiyon zaten yoksa (404) silinecek bir şey yoktur.
     */
    public function indeksiSifirla(): void
    {
        $yanit = $this->istek()->delete("{$this->baseUrl}/collections/".self::COLLECTION_NAME);

        if ($yanit->failed() && $yanit->status() !== 404) {
            $yanit->throw();
        }

        $this->koleksiyonuUnut();

        KnowledgeRecord::query()->update(['chroma_id' => null, 'indekslendi' => null]);
    }
}
