<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KnowledgeRecord;
use App\Services\ActivityRecorder;
use App\Services\KnowledgeBaseService;
use App\Support\Company;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Kurumsal sitedeki bilgileri bilgi bankasına taşır.
 *
 * NEDEN: teklif yazan model yalnızca bilgi bankasındaki kuralları bilir;
 * sitede duran hizmet tanımları ve sık sorulanlar bu komut olmadan dışarıda
 * kalır. Müşteri "garanti süresi ne", "kurulum dahil mi", "teslim ne zaman"
 * diye sorduğunda model ya kaçamak yanıt verir ya uydurur.
 *
 * MODEL EĞİTİLMEZ, BAĞLAM BESLENİR. Kalıcı bilgi modelin ağırlıklarına değil
 * bilgi bankasına yazılır: kapsam değiştiğinde tek bir kaydı düzeltmek yeter.
 *
 * YAPAY ZEKÂ ÇAĞRISI YOK: iş yalnız veri çekimi. Yapılan iş HTML okumak ve
 * metni OLDUĞU GİBİ yazmak: özetleyen,
 * yeniden yazan ya da yorumlayan bir adım yok. Vektör indeksi de burada
 * üretilmez; onu saat başı koşan `knowledge:index` turu yapar.
 *
 * HEPSİ YA DA HİÇBİRİ. Sayfalar önce baştan sona OKUNUR ve doğrulanır; bu
 * aşamada tek satır yazılmaz. Bir tek sayfa okunamazsa ya da şüpheli
 * görünürse tur tümüyle iptal edilir: veri bütünlüğü kısmi bir güncellemeden
 * önce gelir. Yarım bir tur bilgi bankasını sitenin bir kısmına göre günceller, geri
 * kalanını eski bırakır ve hangi kaydın güncel olduğu belirsizleşir.
 *
 * SİTE TASARIMI DEĞİŞİR. Çıkarım belirli bir şablona değil HTML'in kendi
 * yapısına bakar (başlıklar + paragraf/madde/soru blokları; menü, üstbilgi ve
 * altbilgi atılır). Yine de içerik bir gün betikle çizilmeye başlarsa sayfa
 * "okundu ama boşaldı" görünür: her sayfanın son başarılı hacmi saklanır ve
 * yeni çıkarım yarıdan fazla küçükse tur iptal edilir.
 *
 * SONUÇ HABER VERİLİR. İptal hata kaydına, değişiklik denetim kaydına yazılır:
 * haftalık bir tur kimsenin bakmadığı bir günlükte sessizce düşerse, bilgi
 * bankasının eskidiği ancak yanlış bir teklifte fark edilir.
 *
 *     php artisan knowledge:site           # okur, doğrular, yazar
 *     php artisan knowledge:site --dry-run    # yalnızca ne olacağını yazar
 *     php artisan knowledge:site --force   # küçülme denetimini yok say
 *
 * SAYFALAR VE SİTE KURULUMDAN GELİR (COMPANY_WEBSITE, storage/app/company/
 * site-pages.json; bkz. config/company.php). Liste yoksa tur hiçbir şeye
 * dokunmadan atlanır.
 */
class KnowledgeSiteCommand extends Command
{
    protected $signature = 'knowledge:site {--dry-run : Yazmadan, yalnızca bulunanları listele}
                                          {--force : Sayfa küçüldüyse de yaz (tasarım değiştiyse)}';

    protected $description = 'Kurumsal sitedeki hizmet bilgilerini bilgi bankasına aktarır';

    /**
     * Kaynak etiketi: bu kayıtların nereden geldiği kayıtta dursun.
     *
     * Açık: yetim ayıklaması (knowledge:index --orphans) site içeriğini bu
     * etiketten tanıyor; değer iki yerde ayrı ayrı yazılırsa ayrışır. Sitenin
     * alan adından türetilir (bkz. Company::siteKaynagi).
     */
    public static function source(): string
    {
        return Company::siteSource();
    }

    /** Bir bölümün kayda değer sayılması için en az bu kadar karakter. */
    private const EN_AZ = 120;

    /**
     * Sayfa bu oranın altına düşerse ŞÜPHELİDİR.
     *
     * Tasarım değişikliği metnin bir kısmını kaydırabilir; bu normaldir. Ama
     * yarıdan fazlası kaybolduysa çıkarım büyük olasılıkla kırılmıştır
     * (içerik betikle çiziliyor, sayfa taşındı, hata sayfası döndü) ve o hâlde
     * eski kayıtları silmek yanlışı düzeltmek değil, doğruyu yok etmek olur.
     */
    private const KUCULME_SINIRI = 0.5;

    /** Son başarılı çıkarımın hacmi sayfa başına saklanır. */
    private const HACIM_ANAHTARI = 'knowledge:site:hacim:';

    /*
     * OKUNACAK SAYFALAR KURULUMDAN GELİR (bkz. config/company.php →
     * site_sayfalari_dosyasi). Hangi sayfaların okunacağı şirkete özgüdür ve
     * depoda durmaz.
     *
     * Fiyat listesi sayfası listeye KONMAMALI: o veri zaten PriceItem/fiyat
     * motorunda yapılandırılmış olarak duruyor. Aynı rakamları düz metin
     * olarak da bilgi bankasına yazmak iki kaynağı birbirinden bağımsız
     * güncellenebilir hale getirir; sitedeki metin eskiyip fiyat motoru
     * güncellenirse RAG yanlış fiyatı "bağlam" diye modele verir.
     */

    public function handle(KnowledgeBaseService $kb, ActivityRecorder $kayit): int
    {
        try {
            $sayfalar = Company::sitePages();
        } catch (RuntimeException $e) {
            return $this->abort($kayit, $e->getMessage());
        }

        /*
         * LİSTE YOKSA HİÇBİR ŞEYE DOKUNULMAZ. Boş listeyle devam etmek
         * "sitede hiçbir bölüm kalmadı" demek olur ve site kayıtlarının
         * hepsi silinirdi.
         */
        if ($sayfalar === []) {
            $this->components->info('Okunacak site sayfası tanımlı değil (storage/app/company/site-pages.json); tur atlandı.');

            return self::SUCCESS;
        }

        /* ---- 1. AŞAMA: OKU ve DOĞRULA. Burada hiçbir şey yazılmaz. ---- */
        $toplanan = [];

        foreach ($sayfalar as $adres => $sayfa) {
            $okunan = $this->readPage($adres, $sayfa);

            if ($okunan === null) {
                return $this->abort($kayit, "{$adres} okunamadı ya da şüpheli görünüyor.");
            }

            $toplanan[$adres] = $okunan;
        }

        if ($this->option('dry-run')) {
            foreach ($toplanan as $sayfa) {
                foreach ($sayfa['bolumler'] as $bolum) {
                    $this->line(sprintf('  %-58s %d karakter', mb_substr($bolum['baslik'], 0, 58), mb_strlen($bolum['metin'])));
                }
            }

            $this->components->info(sprintf(
                'Kuru koşu: %d sayfa, %d bölüm okundu; hiçbir şey yazılmadı.',
                count($toplanan),
                array_sum(array_map(static fn (array $s): int => count($s['bolumler']), $toplanan)),
            ));

            return self::SUCCESS;
        }

        /* ---- 2. AŞAMA: YAZ. Tek işlem: yarım kalan tur iz bırakmasın. ---- */
        $sayac = ['eklenen' => 0, 'guncellenen' => 0, 'degismeyen' => 0, 'silinen' => 0];

        DB::transaction(function () use ($toplanan, &$sayac, $kb): void {
            $gorulen = [];

            foreach ($toplanan as $sayfa) {
                foreach ($sayfa['bolumler'] as $bolum) {
                    $gorulen[] = $bolum['baslik'];
                    $this->write($bolum, $sayfa['kategori'], $sayac);
                }
            }

            $sayac['silinen'] = $this->deleteStale($kb, $gorulen);
        });

        foreach ($toplanan as $adres => $sayfa) {
            Cache::forever(self::HACIM_ANAHTARI.md5($adres), $sayfa['hacim']);
        }

        $ozet = sprintf(
            'Site taraması: %d eklendi, %d güncellendi, %d silindi, %d değişmedi (%d sayfa).',
            $sayac['eklenen'], $sayac['guncellenen'], $sayac['silinen'], $sayac['degismeyen'], count($toplanan),
        );

        $this->components->info($ozet);

        /*
         * DEĞİŞİKLİK KAYDA GEÇER, değişmeyen tur geçmez: her hafta "hiçbir şey
         * olmadı" satırı yazmak denetim kaydını gürültüye boğar ve içindeki
         * gerçek olayları görünmez kılar.
         */
        if ($sayac['eklenen'] + $sayac['guncellenen'] + $sayac['silinen'] > 0) {
            $kayit->log('bilgi_site_tarama', $ozet);
        }

        return self::SUCCESS;
    }

    /**
     * Tur iptal edildi: kimse bakmasa da HABER VERİLİR.
     *
     * Hata kaydına düşer (yönetici panelden görür) ve dosya günlüğüne yazılır.
     * Sessizce iptal edilen bir tur, bilgi bankasının eskidiğini ancak yanlış
     * bir teklifte fark ettirirdi.
     */
    private function abort(ActivityRecorder $kayit, string $sebep): int
    {
        $mesaj = "Site taraması iptal edildi: {$sebep} Hiçbir kayıt değiştirilmedi.";

        $this->components->error($mesaj);

        Log::warning($mesaj);
        report(new RuntimeException($mesaj));
        $kayit->log('bilgi_site_tarama_iptal', $mesaj);

        return self::FAILURE;
    }

    /**
     * Bir sayfayı okur ve DOĞRULAR. Sorun varsa null döner; çağıran turu iptal
     * eder.
     *
     * @param  array{baslik: string, kategori: string}  $sayfa
     * @return array{kategori: string, hacim: int, bolumler: list<array{baslik: string, metin: string}>}|null
     */
    private function readPage(string $adres, array $sayfa): ?array
    {
        try {
            $html = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'SYS/1.0 (bilgi bankası aktarımı)'])
                ->get($adres)
                ->throw()
                ->body();
        } catch (Throwable $e) {
            $this->components->error("{$adres} alınamadı: ".$e->getMessage());

            return null;
        }

        $bolumler = array_values(array_filter(
            $this->splitIntoQuestions($this->sections($html)),
            fn (array $bolum): bool => mb_strlen($bolum['metin']) >= self::EN_AZ,
        ));

        if ($bolumler === []) {
            $this->components->error("{$adres}: okunabilir içerik çıkmadı (sayfa taşınmış ya da içerik betikle çiziliyor olabilir).");

            return null;
        }

        /* Başlıklar sayfa adıyla birlikte tekilleşir. */
        $bolumler = array_map(fn (array $bolum): array => [
            'baslik' => "{$sayfa['baslik']} — {$bolum['baslik']}",
            'metin' => $bolum['metin'],
        ], $bolumler);

        $hacim = array_sum(array_map(static fn (array $b): int => mb_strlen($b['metin']), $bolumler));
        $onceki = (int) Cache::get(self::HACIM_ANAHTARI.md5($adres), 0);

        if (! $this->option('force') && $onceki > 0 && $hacim < $onceki * self::KUCULME_SINIRI) {
            $this->components->error(sprintf(
                '%s: içerik %d karakterden %d karaktere düştü — çıkarım kırılmış olabilir. (--force ile yazdırılır.)',
                $adres, $onceki, $hacim,
            ));

            return null;
        }

        return ['kategori' => $sayfa['kategori'], 'hacim' => $hacim, 'bolumler' => $bolumler];
    }

    /**
     * Tek bir bölümü yazar.
     *
     * @param  array{baslik: string, metin: string}  $bolum
     * @param  array<string, int>  $sayac
     */
    private function write(array $bolum, string $kategori, array &$sayac): void
    {
        $kayit = KnowledgeRecord::query()->where('baslik', $bolum['baslik'])->first();

        if ($kayit === null) {
            KnowledgeRecord::create([
                'baslik' => $bolum['baslik'],
                'kategori' => $kategori,
                'metin' => $bolum['metin'],
                'kaynak' => self::source(),
            ]);

            $sayac['eklenen']++;

            return;
        }

        /*
         * ELLE YAZILMIŞ KAYIT EZİLMEZ.
         *
         * Satış ekibinin kendi eklediği bir kayıt aynı başlığı taşıyorsa site
         * metni onun üstüne yazmamalı; siteden gelenler kaynağından tanınır.
         */
        if ($kayit->kaynak !== self::source()) {
            $sayac['degismeyen']++;

            return;
        }

        if ($kayit->metin === $bolum['metin'] && $kayit->kategori === $kategori) {
            $sayac['degismeyen']++;

            return;
        }

        $kayit->update(['metin' => $bolum['metin'], 'kategori' => $kategori]);
        $sayac['guncellenen']++;
    }

    /**
     * SİTEDEN KALKAN BÖLÜM BİLGİ BANKASINDAN DA KALKAR.
     *
     * Sayfa yeniden düzenlendiğinde eski bölümler kayıt olarak kalırsa
     * aramada çıkmaya devam eder; sitede olmayan bir metni müşteriye
     * söylemek yanlış bilgi vermektir.
     *
     * Buraya ancak BÜTÜN sayfalar okunabildiğinde gelinir (tur hepsi-ya-da-
     * hiçbiri); yarım bir okumayla silme yapılmaz.
     *
     * @param  list<string>  $gorulen
     */
    private function deleteStale(KnowledgeBaseService $kb, array $gorulen): int
    {
        $eskiler = KnowledgeRecord::query()
            ->where('kaynak', self::source())
            ->whereNotIn('baslik', $gorulen)
            ->get();

        foreach ($eskiler as $eski) {
            $kb->deleteEntry($eski->id);
        }

        return $eskiler->count();
    }

    /**
     * SIK SORULANLAR SORU SORU AYRILIR.
     *
     * Bütün SSS tek kayıt olarak durduğunda arama tek bir soruyu değil sayfanın
     * tamamını getiriyor. Soru satırı (soru işaretiyle biten kısa satır) yeni
     * bir kaydın başlığı olur.
     *
     * @param  list<array{baslik: string, metin: string}>  $bolumler
     * @return list<array{baslik: string, metin: string}>
     */
    private function splitIntoQuestions(array $bolumler): array
    {
        $cikti = [];

        foreach ($bolumler as $bolum) {
            if (! str_contains(mb_strtolower($bolum['baslik']), 'sık sorulan')) {
                $cikti[] = $bolum;

                continue;
            }

            $soru = null;
            $yanit = [];

            foreach (explode("\n", $bolum['metin']) as $satir) {
                $satir = trim($satir);

                if ($satir === '') {
                    continue;
                }

                if (str_ends_with($satir, '?') && mb_strlen($satir) < 140) {
                    if ($soru !== null && $yanit !== []) {
                        $cikti[] = ['baslik' => $soru, 'metin' => $soru."\n".implode(' ', $yanit)];
                    }

                    $soru = $satir;
                    $yanit = [];

                    continue;
                }

                $yanit[] = $satir;
            }

            if ($soru !== null && $yanit !== []) {
                $cikti[] = ['baslik' => $soru, 'metin' => $soru."\n".implode(' ', $yanit)];
            }

            if ($soru === null) {
                $cikti[] = $bolum;
            }
        }

        return $cikti;
    }

    /**
     * Sayfayı BAŞLIKLARINA göre böler.
     *
     * Sayfanın tamamını tek kayda koymak aramayı körleştirir: "garanti"
     * sorusu bütün hizmetler sayfasını getirir ve modelin okuması gereken
     * metin gereksizce büyür.
     *
     * @return list<array{baslik: string, metin: string}>
     */
    private function sections(string $html): array
    {
        $belge = new DOMDocument;
        $onceki = libxml_use_internal_errors(true);
        $belge->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        $xpath = new DOMXPath($belge);

        /* Gezinme, altbilgi ve betikler içerik değildir. */
        foreach ($xpath->query('//nav | //footer | //header | //script | //style | //noscript') as $dugum) {
            $dugum->parentNode?->removeChild($dugum);
        }

        $bolumler = [];
        $baslik = 'Genel';
        $tampon = [];

        /*
         * `summary` DE BAŞLIKTIR: sık sorulanlar sayfası açılır kapanır
         * bloklar kullanır ve soru orada durur. Etiket listede olmasaydı
         * kayda yalnızca yanıtlar girerdi; soruyu taşımayan bir yanıt
         * aramada bulunamaz.
         */
        $dugumler = $xpath->query('//h1 | //h2 | //h3 | //h4 | //summary | //p | //li | //dt | //dd');

        foreach ($dugumler as $dugum) {
            if (! $dugum instanceof DOMElement) {
                continue;
            }

            $metin = trim(preg_replace('/\s+/u', ' ', $dugum->textContent) ?? '');

            if ($metin === '') {
                continue;
            }

            if (in_array($dugum->nodeName, ['h1', 'h2', 'h3', 'h4', 'summary'], true)) {
                if ($tampon !== []) {
                    $bolumler[] = ['baslik' => $baslik, 'metin' => implode("\n", $tampon)];
                }

                $baslik = mb_substr($metin, 0, 120);
                $tampon = [];

                continue;
            }

            /* Aynı cümle menüde ve gövdede iki kez geçebiliyor. */
            if (! in_array($metin, $tampon, true)) {
                $tampon[] = $metin;
            }
        }

        if ($tampon !== []) {
            $bolumler[] = ['baslik' => $baslik, 'metin' => implode("\n", $tampon)];
        }

        /*
         * SORU, YANITIN İÇİNE DE YAZILIR: arama metne bakıyor ve soruyu tekrar
         * etmeyen bir yanıt o soruyla eşleşmez.
         */
        return array_map(static function (array $bolum): array {
            if (str_ends_with($bolum['baslik'], '?') && ! str_starts_with($bolum['metin'], $bolum['baslik'])) {
                $bolum['metin'] = $bolum['baslik']."\n".$bolum['metin'];
            }

            return $bolum;
        }, $bolumler);
    }
}
