<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KnowledgeRecord;
use App\Services\KnowledgeBaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bilgi bankasının vektör indeksini veritabanından yeniden kurar.
 *
 * NEDEN VAR: asıl veri veritabanında, Chroma ondan türetilen indeks. İndeks
 * kaybolabilir (konteyner yeniden kurulur, hacim silinir) ya da bir kayıt
 * yazılırken Chroma erişilemez olabilir. İkisinde de veri kaybı YOK; eksik
 * olan yalnızca indekstir ve bu komut onu kapatır.
 *
 * TERSİNE DE BAKAR: Chroma'da olup hiçbir kayda ait olmayan (YETİM) vektör
 * aramaya karışır ve modele silinmiş ya da eskimiş bilgi söyletir. Her tur
 * yetimleri arar (bkz. yetimleriTemizle).
 *
 *     php artisan bilgi:indeksle              # eksik/değişmiş olanlar + yetim taraması
 *     php artisan bilgi:indeksle --tumu       # hepsini yeniden yaz
 *     php artisan bilgi:indeksle --yetimler   # dışarıdan yazılmış yetimleri ayıkla
 */
class KnowledgeIndexCommand extends Command
{
    protected $signature = 'bilgi:indeksle {--tumu : Değişmemiş kayıtları da yeniden indeksle}
                                           {--sifirla : İndeksi sil ve baştan kur (gömme modeli değişince ŞART)}
                                           {--yetimler : Dışarıdan yazılmış yetim vektörleri veritabanına al ya da sil}';

    protected $description = 'Bilgi bankasının Chroma indeksini veritabanından kurar';

    public function handle(KnowledgeBaseService $kb): int
    {
        /*
         * SIFIRLAMA: gömme modeli değiştiğinde eski vektörler başka bir
         * uzaydadır ve silinmedikçe sonuçları bozar.
         */
        if ($this->option('sifirla')) {
            try {
                $kb->indeksiSifirla();
            } catch (Throwable $e) {
                $this->components->error('İndeks silinemedi; kayıtlara dokunulmadı: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->components->info('İndeks silindi; kayıtlar baştan yazılacak.');
        }

        /* İndekslemeden ÖNCE: içeri alınan kayıtlar bu turda yazılsın. */
        if ($this->option('yetimler') && ! $this->yabancilariAyikla($kb)) {
            return self::FAILURE;
        }

        $indekslendi = $this->indeksle($kb);
        $yetimsiz = $this->yetimleriTemizle($kb);

        /*
         * Eksik kalan varsa komut BAŞARISIZ döner: zamanlayıcının çıktısı ve
         * dönüş kodu bunu görünür kılsın, sessizce "tamam" demesin.
         */
        return $indekslendi && $yetimsiz ? self::SUCCESS : self::FAILURE;
    }

    /** Eksik ya da değişmiş kayıtları Chroma'ya yazar; hepsi yazıldıysa true. */
    private function indeksle(KnowledgeBaseService $kb): bool
    {
        $kayitlar = $this->option('tumu') || $this->option('sifirla')
            ? KnowledgeRecord::query()->get()
            : KnowledgeRecord::query()->indekslenmemis()->get();

        if ($kayitlar->isEmpty()) {
            $this->components->info('İndekslenecek kayıt yok.');

            return true;
        }

        $basarili = 0;

        foreach ($kayitlar as $kayit) {
            if ($kb->indeksle($kayit)) {
                $basarili++;
            }
        }

        $this->components->info("İndekslenen: {$basarili}/{$kayitlar->count()}");

        return $basarili === $kayitlar->count();
    }

    /**
     * YETİM TARAMASI — her turda.
     *
     * deleteEntry, Chroma o an erişilemezse vektörü bırakır; onu bu tur
     * temizler. İki tür yetim var:
     *
     * - BİZİM yazdığımız (üstveride kayit_id var): kaydı silinmiş ya da kayıt
     *   başka bir kimlikle yeniden yazılmış. Asıl veri veritabanı olduğu için
     *   KENDİLİĞİNDEN silinir.
     * - DIŞARIDAN yazılmış (kayit_id yok): içinde veritabanında olmayan bilgi
     *   olabilir, kendiliğinden SİLİNMEZ. Tur başarısız döner ve --yetimler'i
     *   gösterir; kararı bir insan verir.
     *
     * ÖNCE CHROMA, SONRA VERİTABANI okunur. Panelden eklenen bir kayıtta önce
     * satır, sonra vektör yazılır; ters sırada okunsaydı o an eklenen kaydın
     * vektörü "satırı yok" sanılıp silinebilirdi.
     */
    private function yetimleriTemizle(KnowledgeBaseService $kb): bool
    {
        try {
            $vektorler = $kb->vektorler();
        } catch (Throwable $e) {
            $this->components->error('Yetim taraması yapılamadı, Chroma okunamadı: '.$e->getMessage());

            return false;
        }

        /** @var array<int, ?string> $kayitlar kayıt kimliği → vektör kimliği */
        $kayitlar = KnowledgeRecord::query()->pluck('chroma_id', 'id')->all();
        $bagli = array_flip(array_filter($kayitlar));

        $bizim = [];
        $yabanci = [];

        foreach ($vektorler as $vektor) {
            if (isset($bagli[$vektor['id']])) {
                continue;
            }

            $kayitId = $this->kayitKimligi($vektor);

            if ($kayitId === null) {
                $yabanci[] = $vektor;

                continue;
            }

            /*
             * Kaydı var ama kimliği henüz yazılmamış: kayıt şu an
             * indeksleniyor (vektör yazıldı, satır güncellenmedi). Bir sonraki
             * tur kararı verir.
             */
            if (array_key_exists($kayitId, $kayitlar) && $kayitlar[$kayitId] === null) {
                continue;
            }

            $bizim[] = $vektor['id'];
        }

        $temiz = true;

        if ($bizim !== []) {
            try {
                $kb->vektorleriSil($bizim);
                $this->components->info('Silinen yetim vektör (kaydı silinmiş ya da yeniden yazılmış): '.count($bizim));
            } catch (Throwable $e) {
                $this->components->error(count($bizim).' yetim vektör silinemedi: '.$e->getMessage());
                $temiz = false;
            }
        }

        if ($yabanci !== []) {
            $this->components->warn(count($yabanci).' vektör dışarıdan yazılmış ve hiçbir kayda ait değil; dokunulmadı:');
            $this->components->bulletList(array_map($this->etiket(...), array_slice($yabanci, 0, 10)));
            $this->components->warn('İncelemek ve ayıklamak için: php artisan bilgi:indeksle --yetimler');
            $temiz = false;
        }

        return $temiz;
    }

    /**
     * DIŞARIDAN YAZILMIŞ YETİMLER (--yetimler).
     *
     * Chroma'ya veritabanı atlanarak yazılmış belgeler (sitenin eski bir
     * metni, başka hiçbir yerde olmayan elle yazılmış ürün notları) aramada
     * çıkar ama ekranda görünmez, düzeltilemez ve yedeğe girmez. Kural:
     *
     * - Kaynağı site olan SİLİNİR: site içeriğinin sahibi bilgi:siteden turu
     *   ve güncel metin onun kayıtlarında. Turun bilmediği bir kopya hiç
     *   güncellenmez, sayfa siteden kalksa da kalkmaz.
     * - Aynı başlık ve kategori veritabanında varsa SİLİNİR: asıl olan kayıttır
     *   ve kendi vektörü vardır.
     * - Metni olmayan SİLİNİR: aramada modele bir şey taşımaz.
     * - Kalanı veritabanına ALINIR ve bu turda yeniden indekslenir. Vektör
     *   aynı kimlikle, kayit_id ile ve güncel gömme modeliyle yeniden yazılır.
     *
     * --chromadan yalnız içeri alır, kopyaları SİLMEZ; bu seçenek ikisini
     * birden yapar.
     */
    private function yabancilariAyikla(KnowledgeBaseService $kb): bool
    {
        try {
            $vektorler = $kb->vektorler(metinle: true);
        } catch (Throwable $e) {
            $this->components->error('Chroma okunamadı: '.$e->getMessage());

            return false;
        }

        $bagli = KnowledgeRecord::query()->whereNotNull('chroma_id')->pluck('id', 'chroma_id')->all();

        $silinecek = [];
        $alinan = 0;

        foreach ($vektorler as $vektor) {
            /* Bağlı olana dokunulmaz; bizim yetimlerimizi tur kendisi temizler. */
            if (isset($bagli[$vektor['id']]) || $this->kayitKimligi($vektor) !== null) {
                continue;
            }

            /* Sütun boyları: baslik/kategori 255, kaynak 60. */
            $baslik = mb_substr($this->alan($vektor, 'title'), 0, 255);
            $kategori = mb_substr($this->alan($vektor, 'category'), 0, 255);
            $kaynak = mb_substr($this->alan($vektor, 'source'), 0, 60);
            $metin = is_string($vektor['metin']) ? $vektor['metin'] : '';

            if ($kaynak === KnowledgeSiteCommand::kaynak()
                || trim($metin) === ''
                || KnowledgeRecord::query()->where('baslik', $baslik)->where('kategori', $kategori)->exists()) {
                $silinecek[] = $vektor['id'];
                $this->line('  sil · '.$this->etiket($vektor));

                continue;
            }

            /*
             * Kimlik korunur: yeniden indekslenince AYNI vektörün üstüne
             * yazılır. Sütun UUID bekliyor; başka biçimdeki bir kimlikle kayıt
             * yeni kimlik alır, eski vektör silinir.
             */
            $uuid = Str::isUuid($vektor['id']);

            KnowledgeRecord::create([
                'baslik' => $baslik,
                'kategori' => $kategori,
                'metin' => $metin,
                'kaynak' => $kaynak !== '' ? $kaynak : 'chroma',
                'chroma_id' => $uuid ? $vektor['id'] : null,
            ]);

            if (! $uuid) {
                $silinecek[] = $vektor['id'];
            }

            $alinan++;
            $this->line('  al  · '.$this->etiket($vektor));
        }

        if ($silinecek !== []) {
            try {
                $kb->vektorleriSil($silinecek);
            } catch (Throwable $e) {
                $this->components->error(count($silinecek).' vektör silinemedi: '.$e->getMessage());

                return false;
            }
        }

        $this->components->info("Dışarıdan yazılmış yetimler · veritabanına alınan: {$alinan} · silinen: ".count($silinecek));

        return true;
    }

    /**
     * Vektörü yazan kaydın kimliği; yoksa null.
     *
     * Yalnızca KnowledgeBaseService::indeksle yazar. Onun yazdığı vektör
     * bizimdir; yazmadığı vektör dışarıdan gelmiştir.
     *
     * @param  array{ustveri: array<string, mixed>}  $vektor
     */
    private function kayitKimligi(array $vektor): ?string
    {
        $kimlik = $this->alan($vektor, 'kayit_id');

        return $kimlik === '' ? null : $kimlik;
    }

    /**
     * Üstverideki bir alan, metin olarak; yoksa boş.
     *
     * Dışarıdan yazılmış üstveride her şey olabilir: metne çevrilemeyen bir
     * değer (dizi) düz bir (string) çevirmede istisnaya dönüşüp turu düşürürdü.
     *
     * @param  array{ustveri: array<string, mixed>}  $vektor
     */
    private function alan(array $vektor, string $anahtar): string
    {
        $deger = $vektor['ustveri'][$anahtar] ?? '';

        return is_scalar($deger) ? (string) $deger : '';
    }

    /** @param  array{ustveri: array<string, mixed>}  $vektor */
    private function etiket(array $vektor): string
    {
        return '['.($this->alan($vektor, 'source') ?: '?').'] '
            .($this->alan($vektor, 'category') ?: '?').' / '
            .($this->alan($vektor, 'title') ?: '(başlıksız)');
    }
}
