<?php

namespace App\Services;

use App\Models\PriceItem;
use App\Support\Company;
use App\Support\ProductGeneration;
use App\Support\QuoteTerms;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Teklif metnini üreten katman: bağlamı toplar, istemi kurar, yanıtı çözer.
 *
 *  - FİYAT SEÇİMİ sabit anahtar kelime listesiyle değil PUANLAMAYLA
 *    yapılır (bkz. ilgiliFiyatKalemleri).
 *  - YANIT BİÇİMİ modele şema olarak verilir; ```json çitlerini temizleyip
 *    parse etmek yedek yoldur (bkz. LlmService).
 */
class QuoteService
{
    /**
     * İstem düzeni: KURALLAR → VERİ → TALEP.
     *
     * Talep en sonda durur: fiyat listesinden ve bilgi bankasından önce
     * yazılırsa uzun listenin ardından model asıl soruyu geride bırakıp
     * listedeki bir ürünü öne çıkarabilir. Sorunun cevaba en yakın yerde
     * durması modelin en iyi hatırladığı yer.
     *
     * Çıktı biçimi ayrıca ŞEMA olarak veriliyor (bkz. YANIT_SEMASI); buradaki
     * satır yalnızca alanların ne anlama geldiğini söylüyor.
     *
     * BU GENEL İSTEMDİR. Şirkete özgü kurallar (ürün adları, hizmete özgü
     * teknik kurallar) depoda durmaz: kurulum storage/app/company/
     * quote-prompt.txt dosyasını koyarsa bunun yerine o kullanılır (bkz.
     * config/company.php, App\Support\Company::istemSablonu). {sirket_adina}
     * şirket adı verilmişse "<ad> adına " olur, verilmemişse boş kalır.
     */
    protected const PROMPT_TEMPLATE = <<<'PROMPT'
Sen {sirket_adina}satış tekliflerini hazırlayan yapay zeka asistanısın.

Görevin: müşteri talebini analiz etmek, FİYAT KALEMLERİ listesinden uygun
kalemleri seçmek, eksik kalan özellikleri ek kalemlerle tamamlamak ve
hesabı doğru yapmak.

TEMEL KURALLAR

1. FİYAT UYDURMA. Yalnızca FİYAT KALEMLERİ listesindeki fiyatları kullan.
   Listede olmayan bir ürün, özellik ya da fiyat ASLA uydurulmaz.

2. Müşteri tam paket adı vermek zorunda değildir. Paketleri yalnızca isme
   göre değil, açıklamalarındaki teknik özelliklere (kapasite, tip, hız,
   adet gibi) göre karşılaştır.

3. Tam uyan paket yoksa en yakın temel paketi seç ve eksik kalanı listedeki
   ek kalemlerle tamamla. Eklediğin her parçanın fiyatını listeden al ve
   kaç adet eklediğini yaz.

4. Adet çarpması: müşteri 2 sunucu istiyorsa sunucu fiyatı 2 ile çarpılır.

5. KDV: kalemde "+KDV" yazıyorsa fiyat KDV hariçtir, "KDV dahil" yazıyorsa
   üstüne KDV ekleme. Listede ne yazıyorsa ona uy. ORANI VARSAYMA: KDV oranı
   GEÇERLİ KOŞULLAR bölümünde yazılıdır, hesabı onunla yap.
   MÜŞTERİYE DURUMU AÇIKÇA YAZ: "+KDV" olan kalemlerde "fiyatlara KDV dahil
   değildir" de ve KDV eklenmiş tutarı ayrı satırda göster. "KDV dahildir"
   ifadesini YALNIZCA kalemde gerçekten KDV dahilse kullan.

6. PARA BİRİMİ karıştırılmaz. Farklı para birimlerindeki kalemleri
   toplamaya çalışma; her birimi ayrı topla ve öyle göster. Kur çevirme.

7. Hesabı kendin yap: ara toplamlar, adetler ve genel toplam doğru olsun.
   Yazmadan önce her satırı listedeki fiyatla bir kez daha karşılaştır.

8. Talebin bir bölümü karşılanıp diğeri karşılanmıyorsa karşılanan bölümü
   yine fiyatlandır, eksik kalanı ayrıca belirt.

9. VARSAYIMINI SÖYLE. Talepte belirsiz kalan bir şeyi kendi seçtiysen bunu
   iç açıklamada yaz.

10. Müşteri mailinde iç teknik tartışmayı gösterme; anlaşılır bir teklif
    sun. Teknik gerekçe, kullanılan paketler, hesaplar ve teyit bekleyen
    noktalar iç açıklamada durur.

11. "Bilgi bankasında yeterli kural bulunamadı" ifadesini yalnızca gerçekten
    gerekli bilgi yoksa kullan. Fiyat listesindeki teknik özelliklerden
    mantıklı bir sonuç çıkarabiliyorsan onu kullan.

12. NESİL UYDURULMAZ. Bir ürünün "yeni nesil", "son nesil" ya da "en yeni"
    olduğunu kendiliğinden yazma: nesil kalemin adında (ör. Gen8, Gen9) ve
    listedeki "Nesil:" satırında yazar. Daha eski nesilli bir kalemi yeni
    nesil diye sunma.
    Müşteri "yeni nesil / en yeni / güncel donanım" istiyorsa aynı
    kategorideki EN YENİ nesilli kalemleri öner. En yeni nesil talebin diğer
    koşullarını karşılamıyorsa bunu AÇIKÇA söyle ve iki seçeneği birlikte
    göster: aranan özelliği karşılayan eski nesil kalem ile en yeni nesil
    kalem. Farkı ve fiyatı yaz, kararı müşteriye bırak.

13. SÜRELERİ SEN YAZMA. Hizmet süresi, kurulum/teslim süresi ve teklifin
    geçerlilik süresi GEÇERLİ KOŞULLAR bölümünde yazılıdır ve teklifin sonuna
    SİSTEM tarafından eklenir. Başka bir süre uydurma; "teklifimiz N gün
    geçerlidir", "aylık faturalandırılır" gibi cümleleri de YAZMA — sondaki
    blok bunu zaten söylüyor, ikinci kez yazılınca teklifte aynı şey iki
    yerde durur. Müşteri süre ya da taahhüt sorduysa yalnızca oradaki bilgiyi
    kullan.

FİYAT KALEMLERİ:
{fiyat_baglami}

BİLGİ BANKASI:
{bilgi_baglami}

{kosullar}

MÜŞTERİ TALEBİ:
{talep}

Yanıtın iki alandan oluşur:
- musteri_maili: müşteriye gönderilecek, resmi ve anlaşılır teklif metni.
- ic_aciklama: satış ekibi için kullanılan paketler, hesaplar, eklenen
  eklentiler, varsayımlar ve teyit gereken teknik noktalar.
PROMPT;

    /**
     * Modelin döndüreceği biçim — metin olarak rica edilmez, ŞEMA verilir.
     *
     * Çitli kod bloğu, açıklama cümlesi, eksik alan: üçünde de kullanıcıya
     * ham metin gider. Şema verildiğinde sağlayıcı biçimi kendi garanti
     * eder.
     */
    protected const YANIT_SEMASI = [
        'type' => 'OBJECT',
        'properties' => [
            'musteri_maili' => ['type' => 'STRING'],
            'ic_aciklama' => ['type' => 'STRING'],
        ],
        'required' => ['musteri_maili', 'ic_aciklama'],
    ];

    /** Modele gidecek en fazla kalem; aşılırsa istem şişer ve dikkat dağılır. */
    protected const AZAMI_KALEM = 120;

    /** Puanlamada eşleşme ağırlıkları: kategori > ad > not. */
    protected const AGIRLIK = ['kategori' => 3, 'ad' => 2, 'not' => 1];

    /**
     * Talepten atılacak sözcükler.
     *
     * "istiyorum", "lütfen", "merhaba" gibi sözcükler her kalemle eşleşmeye
     * çalışıp puanı bozar.
     */
    protected const DOLGU = [
        'istiyorum', 'istiyoruz', 'lazım', 'gerekiyor', 'merhaba', 'iyi', 'günler',
        'teklif', 'fiyat', 'için', 'olsun', 'olur', 'adet', 'tane', 'lütfen',
        'talep', 'ediyorum', 'rica', 'ederim', 'bir', 'bize', 'bana',
    ];

    /**
     * 3 karakterden kısa ama anlamlı teknik kısaltmalar.
     *
     * "IP" iki harf olduğu için bu liste olmadan alttaki 3+ karakter
     * filtresine takılır, IP kalemleri modele gitmez ve model "fiyat
     * listesinde yok" der. Genel filtreyi 2 karaktere indirmek yerine dar
     * bir istisna listesi kullanılır: "ve", "de", "mi"
     * gibi 2 harfli Türkçe bağlaçlar/ekler açılsaydı her kalemle eşleşirdi
     * (ör. "ve" "Veri Merkezi" içinde alt dize olarak geçer).
     *
     * Kısa terim TAM SÖZCÜK olarak eşleşir (bkz. geciyor).
     */
    protected const KISA_TERIMLER = ['ip'];

    public function __construct(
        protected LlmService $llm,
        protected KnowledgeBaseService $knowledgeBase,
    ) {}

    public function generateTeklif(string $talep): array
    {
        $kalemler = $this->ilgiliFiyatKalemleri($talep);
        $modeleGiden = $kalemler->take(self::AZAMI_KALEM);

        $kbResults = $this->knowledgeBase->query($talep, 8);

        $prompt = $this->buildPrompt($talep, $modeleGiden, $kbResults, $kalemler->count());

        /* Biçim modele ŞEMA olarak verilir; metin ricası değil. */
        $raw = $this->llm->generateText($prompt, LlmService::sistemIstemi(), self::YANIT_SEMASI);

        $parsed = $this->parseResponse($raw);

        /*
         * SÜRELER METNE BURADA GİRER.
         *
         * İstemde de yazılı ama orada kalması "model yazarsa yazar" demekti;
         * model yazmayınca teklif süresiz gider. Fiyat geçen her yanıta
         * eklenir, iki kez eklenmez.
         */
        $parsed['musteri_maili'] = QuoteTerms::mailEkle($parsed['musteri_maili']);

        $parsed['kullanilan_bilgi_sayisi'] = count($kbResults);
        $parsed['kullanilan_fiyat_kalemi_sayisi'] = $modeleGiden->count();

        return $parsed;
    }

    protected function buildPrompt(string $talep, Collection $priceItems, array $kbResults, ?int $toplamKalem = null): string
    {
        $fiyatlar = $this->formatPriceItems($priceItems);

        /*
         * KIRPILDIYSA MODEL BİLSİN.
         *
         * Liste sessizce kesilirse model, görmediği bir kalemi "yok" sayıp
         * "karşılanamıyor" yazabilir. Kaç kalemin dışarıda kaldığını söylemek,
         * modelin emin olmadığında teyit istemesini sağlar.
         */
        if ($toplamKalem !== null && $toplamKalem > $priceItems->count()) {
            $disarida = $toplamKalem - $priceItems->count();

            $fiyatlar .= "\n\n(NOT: talebe en yakın {$priceItems->count()} kalem listelendi; "
                ."{$disarida} kalem daha var. Aradığın kalem listede yoksa uydurma, "
                .'satış ekibinden teyit iste.)';
        }

        $ad = Company::ad();

        return strtr($this->sablon(), [
            '{sirket_adina}' => $ad === '' ? '' : "{$ad} adına ",
            '{talep}' => $talep,
            '{fiyat_baglami}' => $fiyatlar,
            '{bilgi_baglami}' => $this->formatKbResults($kbResults),
            '{kosullar}' => QuoteTerms::istemMetni(),
        ]);
    }

    /**
     * Kullanılacak istem şablonu: kurulumun şirkete özgü istemi varsa o,
     * yoksa koddaki genel istem.
     *
     * Dosya her teklifte yeniden okunur: istem değiştirildiğinde panelin
     * yeniden başlatılması gerekmez. Küçük bir dosya okuması, dakikalar
     * süren bir LLM çağrısının yanında ölçülemez.
     */
    protected function sablon(): string
    {
        return Company::istemSablonu() ?? self::PROMPT_TEMPLATE;
    }

    protected function parseResponse(string $raw): array
    {
        $cleaned = trim($raw);

        if (Str::startsWith($cleaned, '```json')) {
            $cleaned = substr($cleaned, strlen('```json'));
        }

        if (Str::startsWith($cleaned, '```')) {
            $cleaned = substr($cleaned, strlen('```'));
        }

        if (Str::endsWith($cleaned, '```')) {
            $cleaned = substr($cleaned, 0, -3);
        }

        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);

        if (! is_array($parsed) || json_last_error() !== JSON_ERROR_NONE) {
            return [
                'musteri_maili' => $raw,
                'ic_aciklama' => 'JSON parse edilemedi, ham LLM yanıtı kullanıldı.',
            ];
        }

        /*
         * ALANLAR EKSİKSE DE HAM METNE DÜŞÜLÜR.
         *
         * Geçerli ama başka alanlı bir JSON ("cevap", "teklif" gibi) kabul
         * edilseydi ekranda boş bir teklif çizilirdi. Şema bunu büyük ölçüde
         * engeller ama yedek sağlayıcıda şema yok.
         */
        $musteri = trim((string) ($parsed['musteri_maili'] ?? ''));
        $ic = trim((string) ($parsed['ic_aciklama'] ?? ''));

        if ($musteri === '') {
            return [
                'musteri_maili' => $raw,
                'ic_aciklama' => 'Yanıt beklenen alanları taşımıyor; ham LLM yanıtı kullanıldı.',
            ];
        }

        return ['musteri_maili' => $musteri, 'ic_aciklama' => $ic] + $parsed;
    }

    protected function formatPriceItems(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '(Fiyat kalemi bulunamadı.)';
        }

        /*
         * NESİL KARŞILAŞTIRMASI KATEGORİ İÇİNDE YAPILIR; sıralama puana göre
         * olduğu için en yeni nesil listenin altında kalabilir ve model onu
         * "yok" sayabilir (bkz. App\Support\ProductGeneration).
         */
        $enYuksekler = ProductGeneration::enYuksekler($items);

        $lines = [];

        foreach ($items as $item) {
            $kdvNotu = $item->kdv_dahil ? 'KDV dahil' : '+KDV';

            $lines[] = "- Kategori: {$item->category}\n"
                ."  Ürün: {$item->name}\n"
                ."  Fiyat: {$item->price} {$item->currency} / {$item->unit}\n"
                ."  KDV: {$kdvNotu}";

            $nesil = ProductGeneration::satir($item, $enYuksekler);

            if ($nesil !== null) {
                $lines[] = $nesil;
            }

            if ($item->notlar) {
                $lines[] = "  Not: {$item->notlar}";
            }
        }

        return implode("\n", $lines);
    }

    protected function formatKbResults(array $results): string
    {
        if (empty($results)) {
            return '(Eşleşen bilgi bankası kaydı bulunamadı.)';
        }

        $lines = [];

        foreach ($results as $result) {
            $metadata = $result['metadata'] ?? [];
            $category = $metadata['category'] ?? 'Genel';
            $title = $metadata['title'] ?? '';
            $text = $result['text'] ?? '';

            $lines[] = "- [{$category}] {$title}: {$text}";
        }

        return implode("\n", $lines);
    }

    /**
     * Talebin ANLAMLI sözcükleri ve ölçü kalıpları.
     *
     * Sabit bir anahtar kelime listesi yerine talebin kendi sözcükleri
     * kullanılır: sabit listede olmayan bir sözcük hiçbir şey eşleştirmez
     * ve bütün fiyat listesi modele gider.
     *
     * Ölçü kalıpları ayrıca çıkarılır ("64 gb", "2 tb", "10 gbps", "/29"):
     * bir kalemin doğru olup olmadığını çoğu zaman bunlar belirler ve
     * sözcük bölmesi onları ikiye ayırır.
     *
     * @return array{sozcukler: list<string>, olculer: list<string>}
     */
    protected function talepSozcukleri(string $talep): array
    {
        $metin = mb_strtolower($talep);

        preg_match_all('/(\d+\s*(?:gb|tb|mb|gbps|mbps|ghz|tl|usd|eur)|\/\d{1,2})/u', $metin, $eslesme);

        $olculer = array_values(array_unique(array_map(
            fn (string $olcu): string => preg_replace('/\s+/', '', $olcu) ?? $olcu,
            $eslesme[1] ?? [],
        )));

        $sozcukler = collect(preg_split('/[^\p{L}\p{N}]+/u', $metin) ?: [])
            ->filter(fn (string $k): bool => mb_strlen($k) >= 3 || in_array($k, self::KISA_TERIMLER, true))
            ->reject(fn (string $k): bool => in_array($k, self::DOLGU, true))
            ->unique()
            ->values()
            ->all();

        return ['sozcukler' => $sozcukler, 'olculer' => $olculer];
    }

    /**
     * Bir kalemin talebe yakınlık puanı.
     *
     * Kategori eşleşmesi en ağır: "backup" diyen biri Backup kategorisinin
     * tamamını görmeli. Ölçü eşleşmesi (64gb, /29) iki kat sayılır; teklifi
     * doğru yapan ayrıntı orada.
     */
    protected function kalemPuani(PriceItem $kalem, array $sozcukler, array $olculer): int
    {
        $kategori = mb_strtolower((string) $kalem->category);
        $ad = mb_strtolower((string) $kalem->name);
        $not = mb_strtolower((string) $kalem->notlar);

        /* Ölçü karşılaştırması boşluksuz yapılır: "64 gb" ile "64gb" aynı şey. */
        $duz = preg_replace('/\s+/', '', $kategori.' '.$ad.' '.$not) ?? '';

        $puan = 0;

        foreach ($sozcukler as $sozcuk) {
            if ($this->geciyor($kategori, $sozcuk)) {
                $puan += self::AGIRLIK['kategori'];
            } elseif ($this->geciyor($ad, $sozcuk)) {
                $puan += self::AGIRLIK['ad'];
            } elseif ($this->geciyor($not, $sozcuk)) {
                $puan += self::AGIRLIK['not'];
            }
        }

        foreach ($olculer as $olcu) {
            if (str_contains($duz, $olcu)) {
                $puan += 2 * self::AGIRLIK['ad'];
            }
        }

        return $puan;
    }

    /**
     * Sözcük metinde geçiyor mu?
     *
     * Uzun sözcük alt dize olarak aranır ("sunucu" → "sunucular"). KISA TERİM
     * ise TAM SÖZCÜK olmalı: "ip" alt dize olarak "Tip 1" içinde de geçer ve
     * her IP sorusu "Tip 1" adlı kalemlerin kategorisini de modele
     * gönderirdi; bir fiyat listesinde "ip"nin alt dize olarak geçtiği
     * kalemler sözcük olarak geçtiklerinden fazla olabilir.
     */
    protected function geciyor(string $metin, string $sozcuk): bool
    {
        if (mb_strlen($sozcuk) >= 3) {
            return str_contains($metin, $sozcuk);
        }

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($sozcuk, '/').'(?![\p{L}\p{N}])/u', $metin) === 1;
    }

    /**
     * Modele gidecek fiyat kalemleri.
     *
     * Üç adım:
     *  1. Her kalem talebe göre puanlanır.
     *  2. Puan alan kalemlerin KATEGORİLERİ bütünüyle alınır — model bir
     *     paketi seçebilmek için komşularını da görmeli (48 GB'lık sunucuyu
     *     seçip 16 GB eklenti ekleyebilmesi buna bağlı).
     *  3. Hiçbir şey puan almazsa liste olduğu gibi döner; talep tanınmadıysa
     *     modeli kör bırakmaktansa hepsini göstermek yeğdir.
     *
     * Sıralama puana göredir: kırpma gerekirse en ilgisiz kalem düşer.
     */
    protected function ilgiliFiyatKalemleri(string $talep): Collection
    {
        $tumu = PriceItem::aktif()->get();

        if ($tumu->isEmpty()) {
            return $tumu;
        }

        ['sozcukler' => $sozcukler, 'olculer' => $olculer] = $this->talepSozcukleri($talep);

        if ($sozcukler === [] && $olculer === []) {
            return $tumu;
        }

        $puanlar = $tumu->mapWithKeys(fn (PriceItem $k): array => [
            $k->id => $this->kalemPuani($k, $sozcukler, $olculer),
        ]);

        $puanliKategoriler = $tumu
            ->filter(fn (PriceItem $k): bool => $puanlar[$k->id] > 0)
            ->pluck('category')
            ->unique()
            ->all();

        if ($puanliKategoriler === []) {
            return $tumu;
        }

        return $tumu
            ->filter(fn (PriceItem $k): bool => $puanlar[$k->id] > 0
                || in_array($k->category, $puanliKategoriler, true))
            ->sortByDesc(fn (PriceItem $k): int => $puanlar[$k->id])
            ->values();
    }
}
