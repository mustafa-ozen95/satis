<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\KnowledgeSiteCommand;
use App\Models\KnowledgeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kurumsal siteden bilgi aktarımı.
 *
 * Model EĞİTİLMİYOR, bağlamı besleniyor: sitedeki anlatım bilgi bankasına
 * kayıt olarak giriyor ve teklif yazılırken aramadan geliyor. Bu yüzden
 * kayıtların doğru bölünmesi (her soru kendi kaydı) ve elle yazılmış
 * kayıtların korunması önemli.
 *
 * Site ve sayfa listesi kurulumdan gelir (config/company.php); burada uydurma
 * bir site ile iki sayfa tanımlanır.
 */
class KnowledgeSiteTest extends TestCase
{
    use RefreshDatabase;

    private string $sayfaDosyasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sayfaDosyasi = (string) tempnam(sys_get_temp_dir(), 'site-sayfalari');
        file_put_contents($this->sayfaDosyasi, json_encode([
            '/veri-merkezi' => ['baslik' => 'Veri merkezi', 'kategori' => 'Veri Merkezi'],
            '/sss' => ['baslik' => 'Sık sorulanlar', 'kategori' => 'SSS'],
        ], JSON_UNESCAPED_UNICODE));

        config([
            'company.website' => 'https://www.ornek.test/',
            'company.site_pages_file' => $this->sayfaDosyasi,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->sayfaDosyasi);

        parent::tearDown();
    }

    private function sayfa(string $govde): void
    {
        Http::fake(['*ornek.test*' => Http::response('<html><body>'.$govde.'</body></html>')]);
    }

    /**
     * SAYFA LİSTESİ YOKSA HİÇBİR ŞEYE DOKUNULMAZ.
     *
     * Boş listeyle devam etmek "sitede bölüm kalmadı" demek olurdu ve bütün
     * site kayıtları silinirdi.
     */
    public function test_sayfa_listesi_yoksa_tur_atlanir_kayitlar_durur(): void
    {
        config(['company.site_pages_file' => $this->sayfaDosyasi.'-yok']);

        KnowledgeRecord::create([
            'baslik' => 'Veri merkezi — Eski Bölüm',
            'kategori' => 'Veri Merkezi',
            'metin' => 'eski metin',
            'kaynak' => 'ornek.test',
        ]);

        Http::fake();

        $this->artisan('knowledge:site')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(1, KnowledgeRecord::query()->count());
    }

    /** Bozuk liste "sayfa yok" sayılmaz: tur iptal edilir ve kayda geçer. */
    public function test_bozuk_sayfa_listesi_turu_iptal_eder(): void
    {
        file_put_contents($this->sayfaDosyasi, '{bozuk');

        $this->artisan('knowledge:site')->assertFailed();

        $this->assertDatabaseHas('activity_logs', ['tur' => 'bilgi_site_tarama_iptal']);
    }

    /** Kaynak etiketi sitenin alan adıdır; baştaki "www." atılır. */
    public function test_kaynak_etiketi_sitenin_alan_adidir(): void
    {
        $this->assertSame('ornek.test', KnowledgeSiteCommand::source());

        config(['company.website' => '']);
        $this->assertSame('kurumsal-site', KnowledgeSiteCommand::source());
    }

    /** Her başlık kendi kaydı olur; kısa bölümler atlanır. */
    public function test_bolumler_ayri_kayit_olur(): void
    {
        $uzun = str_repeat('Veri merkezi altyapısı hakkında ayrıntılı bilgi. ', 5);

        $this->sayfa("<h2>Enerji Altyapısı</h2><p>{$uzun}</p><h2>Kısa</h2><p>az</p>");

        $this->artisan('knowledge:site')->assertSuccessful();

        $this->assertDatabaseHas('bilgi_kayitlari', ['kaynak' => 'ornek.test']);
        $this->assertTrue(KnowledgeRecord::query()->where('baslik', 'like', '%Enerji Altyapısı')->exists());
        $this->assertFalse(KnowledgeRecord::query()->where('baslik', 'like', '%— Kısa')->exists());
    }

    /**
     * SIK SORULANLAR SORU SORU AYRILIR.
     *
     * Sayfa açılır kapanır bloklar kullanıyor: soru `summary` içinde. Etiket
     * okunmazsa kayıtlara yalnızca yanıtlar giriyor ve soruyu taşımayan bir
     * yanıt aramada bulunamıyor.
     */
    public function test_sik_sorulanlar_soru_basina_kayit_olur(): void
    {
        $yanit = str_repeat('Evet, hizmet kapsamında sunulmaktadır. ', 5);

        $this->sayfa(
            '<h2>Sık Sorulan Sorular</h2>'
            ."<details><summary>Teknik destek var mı?</summary><p>{$yanit}</p></details>"
            ."<details><summary>IP adresi dahil mi?</summary><p>{$yanit}</p></details>"
        );

        $this->artisan('knowledge:site')->assertSuccessful();

        $this->assertTrue(KnowledgeRecord::query()->where('baslik', 'like', '%Teknik destek var mı?')->exists());
        $this->assertTrue(KnowledgeRecord::query()->where('baslik', 'like', '%IP adresi dahil mi?')->exists());

        /* Soru yanıtın içinde de durur: arama metne bakıyor. */
        $kayit = KnowledgeRecord::query()->where('baslik', 'like', '%IP adresi dahil mi?')->first();
        $this->assertStringContainsString('IP adresi dahil mi?', (string) $kayit->metin);
    }

    /** Elle yazılmış kayıt EZİLMEZ; site metni onun üstüne yazmaz. */
    public function test_elle_yazilan_kayit_korunur(): void
    {
        $uzun = str_repeat('Site metni burada duruyor. ', 6);

        KnowledgeRecord::create([
            'baslik' => 'Veri merkezi — Enerji Altyapısı',
            'kategori' => 'Veri Merkezi',
            'metin' => 'Satış ekibinin kendi notu.',
            'kaynak' => 'satis-ekibi',
        ]);

        $this->sayfa("<h2>Enerji Altyapısı</h2><p>{$uzun}</p>");

        $this->artisan('knowledge:site')->assertSuccessful();

        $this->assertSame(
            'Satış ekibinin kendi notu.',
            KnowledgeRecord::query()->where('baslik', 'Veri merkezi — Enerji Altyapısı')->value('metin'),
        );
    }

    /**
     * SİTEDEN KALKAN BÖLÜM KAYITTAN DA KALKAR.
     *
     * Sayfa yeniden düzenlendiğinde eski bölüm kayıt olarak kalıyor ve
     * aramada çıkmaya devam ediyordu; sitede olmayan bir metni müşteriye
     * söylemek yanlış bilgi vermektir.
     */
    public function test_sitede_kalmayan_kayit_silinir(): void
    {
        $uzun = str_repeat('Güncel bölüm metni burada. ', 6);

        KnowledgeRecord::create([
            'baslik' => 'Veri merkezi — Kaldırılmış Bölüm',
            'kategori' => 'Veri Merkezi',
            'metin' => 'eski metin',
            'kaynak' => 'ornek.test',
        ]);

        $this->sayfa("<h2>Enerji Altyapısı</h2><p>{$uzun}</p>");

        $this->artisan('knowledge:site')->assertSuccessful();

        $this->assertFalse(
            KnowledgeRecord::query()->where('baslik', 'Veri merkezi — Kaldırılmış Bölüm')->exists(),
        );
    }

    /**
     * SAYFA OKUNAMAZSA TUR TÜMÜYLE İPTAL.
     *
     * Yarım bir tur bilgi bankasını sitenin bir kısmına göre günceller, geri
     * kalanını eski bırakır ve hangi kaydın güncel olduğu belirsizleşir.
     */
    public function test_sayfa_alinamazsa_islem_iptal_edilir(): void
    {
        KnowledgeRecord::create([
            'baslik' => 'Veri merkezi — Eski Bölüm',
            'kategori' => 'Veri Merkezi',
            'metin' => 'eski metin',
            'kaynak' => 'ornek.test',
        ]);

        Http::fake(['*ornek.test*' => Http::response('', 503)]);

        $this->artisan('knowledge:site')->assertFailed();

        /* Ne silindi ne eklendi: kayıt olduğu gibi duruyor. */
        $this->assertTrue(
            KnowledgeRecord::query()->where('baslik', 'Veri merkezi — Eski Bölüm')->exists(),
        );
        $this->assertSame(1, KnowledgeRecord::query()->count());
    }

    /**
     * BİR SAYFA BİLE OKUNAMAZSA HİÇBİRİ YAZILMAZ.
     *
     * Okunabilen sayfaların yazılıp okunamayanların atlanması, bilgi
     * bankasını iki farklı zamandan derlenmiş hâle getirirdi.
     */
    public function test_tek_sayfa_bozuksa_digerleri_de_yazilmaz(): void
    {
        $uzun = str_repeat('Güncel bölüm metni burada duruyor. ', 6);

        Http::fake([
            '*ornek.test/sss*' => Http::response('<html><body></body></html>'),
            '*ornek.test*' => Http::response("<html><body><h2>Enerji Altyapısı</h2><p>{$uzun}</p></body></html>"),
        ]);

        $this->artisan('knowledge:site')->assertFailed();

        $this->assertSame(0, KnowledgeRecord::query()->count(), 'İptal edilen turda hiçbir kayıt yazılmamalıydı.');
    }

    /** İptal SESSİZ OLMAZ: hata kaydına ve denetim kaydına düşer. */
    public function test_iptal_kayda_gecer(): void
    {
        Http::fake(['*ornek.test*' => Http::response('', 503)]);

        $this->artisan('knowledge:site')->assertFailed();

        $this->assertDatabaseHas('activity_logs', ['tur' => 'bilgi_site_tarama_iptal']);
    }
}
