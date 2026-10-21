<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PriceItem;
use App\Services\KnowledgeBaseService;
use App\Services\LlmService;
use App\Services\QuoteService;
use App\Support\QuoteTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * TEKLİFİN SABİT KOŞULLARI: SÜRELER VE KDV ORANI.
 *
 * Modele bırakılan bir teklif iki şeyi birden kaçırabiliyor:
 *
 *  - SÜRE YOK. Fiyat "1.250,00 TL / ay" yazıyor ama hizmetin ne kadar
 *    süreyle verildiği, ne zaman teslim edileceği yazmıyor.
 *  - KDV ORANI UYDURULMUŞ: model ayardaki oran yerine kendi bildiği bir
 *    oranı varsayıyor. Oran bilgi bankasında dursa bile oraya ANLAM
 *    ARAMASIYLA ulaşılıyor, "16gb ram sunucu öner" sorgusu ödeme
 *    koşullarını hiç getirmiyor.
 *
 * Her teklifte geçerli olan bir koşul, arama sonucuna ya da modelin o günkü
 * hâline bağlı olamaz: koşullar isteme HER ZAMAN girer ve müşteri mailinin
 * sonuna SİSTEM ekler.
 */
class QuoteTermsTest extends TestCase
{
    use RefreshDatabase;

    /** SÜRELER MAİLİN SONUNDA DURUR — model yazsın diye beklenmez. */
    public function test_fiyat_gecen_teklifin_sonuna_sureler_eklenir(): void
    {
        $sonuc = $this->uret('sunucu istiyorum', 'Aylık 1.250,00 TL tutarındadır.');

        $this->assertStringContainsString(QuoteTerms::BASLIK, $sonuc['musteri_maili']);
        $this->assertStringContainsString('Hizmet süresi:', $sonuc['musteri_maili']);
        $this->assertStringContainsString('Kurulum / teslim süresi:', $sonuc['musteri_maili']);
        $this->assertStringContainsString('Teklif geçerlilik süresi: 30 gün', $sonuc['musteri_maili']);
    }

    /** Süreler AYARDAN gelir; metne gömülü değildir. */
    public function test_sureler_ayardan_okunur(): void
    {
        config([
            'quote.hizmet_suresi' => '12 ay taahhütlüdür.',
            'quote.gecerlilik_gun' => 7,
        ]);

        $sonuc = $this->uret('sunucu istiyorum', '1.000,00 TL / ay');

        $this->assertStringContainsString('12 ay taahhütlüdür.', $sonuc['musteri_maili']);
        $this->assertStringContainsString('Teklif geçerlilik süresi: 7 gün', $sonuc['musteri_maili']);
    }

    /**
     * BİLGİ SORUSUNA EKLENMEZ.
     *
     * Panel teklif dışında bilgi sorularına da cevap veriyor ("iletişim
     * bilgileriniz nedir"); adresin altına hizmet süresi eklemek gürültüdür.
     */
    public function test_fiyat_gecmeyen_yanita_eklenmez(): void
    {
        $sonuc = $this->uret(
            'iletişim bilgileriniz nedir',
            "Telefon: +90 000 000 00 00\nE-posta: bilgi@ornek.test",
        );

        $this->assertStringNotContainsString(QuoteTerms::BASLIK, $sonuc['musteri_maili']);
    }

    /** Model kurala uymayıp kendi yazdıysa İKİNCİ KOPYA çıkmaz. */
    public function test_iki_kez_eklenmez(): void
    {
        $sonuc = $this->uret('sunucu', "500,00 TL / ay\n\n".QuoteTerms::mailMetni());

        $this->assertSame(1, substr_count($sonuc['musteri_maili'], QuoteTerms::BASLIK));
    }

    /** KDV ORANI İSTEMDE YAZAR: model kendi başına bir oran varsaymasın. */
    public function test_kdv_orani_isteme_girer(): void
    {
        config(['quote.kdv_orani' => 20]);

        $istem = $this->istem('sunucu istiyorum');

        $this->assertStringContainsString('KDV oranı: %20', $istem);
        $this->assertStringContainsString('ORANI VARSAYMA', $istem);
    }

    /** Süreler istemde de yazar; model başka bir süre uydurmasın. */
    public function test_sureler_isteme_girer(): void
    {
        $istem = $this->istem('sunucu istiyorum');

        $this->assertStringContainsString('GEÇERLİ KOŞULLAR', $istem);
        $this->assertStringContainsString('Kurulum / teslim süresi:', $istem);
    }

    /* ---------------------------------------------------------------- */
    /*  Şirket adı ve şirkete özgü istem kurulumdan gelir. */
    /* ---------------------------------------------------------------- */

    /** Şirket adı verilmemişse istemde ad geçmez; verilmişse geçer. */
    public function test_sirket_adi_ayardan_gelir(): void
    {
        config(['company.ad' => '']);
        $istem = $this->istem('sunucu istiyorum');
        $this->assertStringStartsWith('Sen satış tekliflerini hazırlayan', $istem);

        config(['company.ad' => 'Örnek Bilişim A.Ş.']);
        $this->assertStringStartsWith('Sen Örnek Bilişim A.Ş. adına satış tekliflerini', $this->istem('sunucu istiyorum'));
        $this->assertStringContainsString('Örnek Bilişim A.Ş. için çalışan', LlmService::sistemIstemi());
    }

    /** Şirkete özgü istem dosyası varsa genel istemin YERİNE geçer. */
    public function test_sirkete_ozgu_istem_dosyasi_kullanilir(): void
    {
        $dosya = $this->istemDosyasi(
            "ÖZEL KURAL: örnek ürün adı kullan.\n{fiyat_baglami}\n{bilgi_baglami}\n{kosullar}\nTALEP: {talep}\n"
        );

        $istem = $this->istem('sunucu istiyorum');

        @unlink($dosya);

        $this->assertStringStartsWith('ÖZEL KURAL: örnek ürün adı kullan.', $istem);
        $this->assertStringEndsWith('TALEP: sunucu istiyorum', $istem, 'Talep yerine konmalı; sondaki boşluk atılır.');
        $this->assertStringContainsString('KDV oranı: %', $istem);
        $this->assertStringNotContainsString('TEMEL KURALLAR', $istem);
    }

    /**
     * YER TUTUCUSU EKSİK DOSYA KULLANILMAZ: model fiyat listesini ya da
     * talebi görmeden teklif yazardı. Genel isteme düşülür.
     */
    public function test_yer_tutucusu_eksik_istem_dosyasi_kullanilmaz(): void
    {
        $dosya = $this->istemDosyasi("ÖZEL KURAL\n{fiyat_baglami}\n{talep}\n");

        $istem = $this->istem('sunucu istiyorum');

        @unlink($dosya);

        $this->assertStringNotContainsString('ÖZEL KURAL', $istem);
        $this->assertStringContainsString('TEMEL KURALLAR', $istem);
    }

    /* ---------------------------------------------------------------- */

    /** Geçici bir istem dosyası yazar ve ayara bağlar. */
    private function istemDosyasi(string $metin): string
    {
        $dosya = (string) tempnam(sys_get_temp_dir(), 'teklif-istemi');
        file_put_contents($dosya, $metin);
        config(['company.istem_dosyasi' => $dosya]);

        return $dosya;
    }

    /** Modeli taklit eder, verilen müşteri mailiyle teklifi üretir. */
    private function uret(string $talep, string $mail): array
    {
        $yanit = json_encode(
            ['musteri_maili' => $mail, 'ic_aciklama' => 'iç'],
            JSON_UNESCAPED_UNICODE,
        );

        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')->once()->andReturn($yanit);

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        return (new QuoteService($llm, $kb))->generateTeklif($talep);
    }

    /** Modele giden istemi yakalar. */
    private function istem(string $talep): string
    {
        PriceItem::factory()->create(['category' => 'Dedicated Sunucu', 'name' => 'Sunucu']);

        $yakalanan = '';

        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')
            ->once()
            ->andReturnUsing(function (string $istem) use (&$yakalanan): string {
                $yakalanan = $istem;

                return '{"musteri_maili":"a","ic_aciklama":"b"}';
            });

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        (new QuoteService($llm, $kb))->generateTeklif($talep);

        return $yakalanan;
    }
}
