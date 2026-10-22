<?php

namespace Tests\Feature;

use App\Models\PriceItem;
use App\Services\KnowledgeBaseService;
use App\Services\LlmService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Teklif üretiminin panel tarafı: bağlam seçimi ve yanıt çözümü.
 *
 * LlmService ve KnowledgeBaseService taklit edilir; gerçek Gemini/Groq/Chroma
 * çağrısı yapılmaz. Buradaki asıl soru "model ne yazdı" değil, MODELE NE
 * GÖNDERDİĞİMİZ.
 */
class QuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_teklif_parses_clean_json_response(): void
    {
        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')
            ->once()
            ->andReturn('{"musteri_maili": "Merhaba", "ic_aciklama": "Detay"}');

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        $service = new QuoteService($llm, $kb);

        $sonuc = $service->generateQuote('2 adet sunucu istiyorum');

        $this->assertSame('Merhaba', $sonuc['musteri_maili']);
        $this->assertSame('Detay', $sonuc['ic_aciklama']);
        $this->assertSame(0, $sonuc['kullanilan_bilgi_sayisi']);
    }

    public function test_generate_teklif_strips_markdown_code_fences(): void
    {
        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')
            ->once()
            ->andReturn("```json\n{\"musteri_maili\": \"Merhaba\", \"ic_aciklama\": \"Detay\"}\n```");

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        $service = new QuoteService($llm, $kb);

        $sonuc = $service->generateQuote('talep');

        $this->assertSame('Merhaba', $sonuc['musteri_maili']);
    }

    public function test_generate_teklif_falls_back_to_raw_text_on_invalid_json(): void
    {
        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')->once()->andReturn('bu json degil');

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        $service = new QuoteService($llm, $kb);

        $sonuc = $service->generateQuote('talep');

        $this->assertSame('bu json degil', $sonuc['musteri_maili']);
        $this->assertStringContainsString('JSON parse edilemedi', $sonuc['ic_aciklama']);
    }

    /**
     * SABİT ANAHTAR KELİME LİSTESİ YOK.
     *
     * Sabit bir listede olmayan sözcük ("kabin") hiçbir şey eşleştirmez ve
     * fiyat listesinin TAMAMI modele giderdi. Talebin kendi sözcükleri
     * kullanılır.
     */
    public function test_listede_olmayan_sozcuk_de_daraltir(): void
    {
        PriceItem::factory()->create(['category' => 'Kabin', 'name' => 'Yarım kabin', 'aktif' => true]);
        PriceItem::factory()->create(['category' => 'Domain', 'name' => '.com', 'aktif' => true]);

        $sonuc = $this->uret('kabin kiralamak istiyorum');

        $this->assertSame(1, $sonuc['kullanilan_fiyat_kalemi_sayisi']);
    }

    /**
     * ÖLÇÜLER EŞLEŞİR: "64 GB" ile kalemdeki "64GB" aynı şeydir; sözcük
     * bölmesi ikisini ayırıyordu.
     */
    public function test_olcu_kaliplari_eslesir(): void
    {
        PriceItem::factory()->create([
            'category' => 'Sunucu', 'name' => 'E5-2620', 'notlar' => '64GB RAM, 2x480 SSD', 'aktif' => true,
        ]);
        PriceItem::factory()->create(['category' => 'Domain', 'name' => '.com', 'aktif' => true]);

        $sonuc = $this->uret('64 GB RAM olan bir makine');

        $this->assertSame(1, $sonuc['kullanilan_fiyat_kalemi_sayisi']);
    }

    /**
     * KOMŞULAR DA GELİR.
     *
     * Model bir paketi seçebilmek için aynı kategorideki diğer paketleri de
     * görmeli: 48 GB'lık sunucuyu seçip eksik RAM'i eklenti ile tamamlaması
     * buna bağlı.
     */
    public function test_puan_alan_kategorinin_tamami_gonderilir(): void
    {
        PriceItem::factory()->create(['category' => 'Backup', 'name' => 'Backup 500 GB', 'aktif' => true]);
        PriceItem::factory()->create(['category' => 'Backup', 'name' => 'Arşiv paketi', 'aktif' => true]);
        PriceItem::factory()->create(['category' => 'Domain', 'name' => '.com', 'aktif' => true]);

        $sonuc = $this->uret('backup istiyorum');

        $this->assertSame(2, $sonuc['kullanilan_fiyat_kalemi_sayisi']);
    }

    /**
     * İKİ HARFLİ "IP" DE DARALTIR, AMA TAM SÖZCÜK OLARAK.
     *
     * 3+ karakter filtresi "ip"yi atıyordu ve IP kalemi modele hiç gitmiyordu.
     * Alt dize olarak aranınca da "Tip 1" gibi adlar yakalanıyordu.
     */
    public function test_iki_harfli_ip_tam_sozcuk_olarak_daraltir(): void
    {
        PriceItem::factory()->create(['category' => 'IP Tahsis', 'name' => '/29 IP bloğu', 'aktif' => true]);
        PriceItem::factory()->create(['category' => 'Kabin Kiralama', 'name' => 'Tip 1: cihaz sayısı başına', 'aktif' => true]);
        PriceItem::factory()->create(['category' => 'Domain', 'name' => '.com', 'aktif' => true]);

        $sonuc = $this->uret('ek ip adresi lazım');

        $this->assertSame(1, $sonuc['kullanilan_fiyat_kalemi_sayisi']);
    }

    /** Hiçbir şey eşleşmezse model kör bırakılmaz: liste olduğu gibi gider. */
    public function test_eslesme_yoksa_tum_liste_gonderilir(): void
    {
        PriceItem::factory()->count(3)->create(['aktif' => true]);

        $sonuc = $this->uret('zzzz yyyy xxxx');

        $this->assertSame(3, $sonuc['kullanilan_fiyat_kalemi_sayisi']);
    }

    /** Modele ŞEMA verilir; biçim ricası değil. */
    public function test_modele_yanit_semasi_verilir(): void
    {
        $sema = null;

        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')
            ->once()
            ->andReturnUsing(function ($prompt, $system = null, $verilen = null) use (&$sema) {
                $sema = $verilen;

                return '{"musteri_maili":"a","ic_aciklama":"b"}';
            });

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        (new QuoteService($llm, $kb))->generateQuote('talep');

        $this->assertIsArray($sema);
        $this->assertContains('musteri_maili', $sema['required']);
    }

    /** Geçerli ama ALANLARI EKSİK JSON sessizce kabul edilmez. */
    public function test_alanlari_eksik_yanit_ham_metne_duser(): void
    {
        $sonuc = $this->uret('talep', '{"cevap":"yanlış alan"}');

        $this->assertStringContainsString('yanlış alan', $sonuc['musteri_maili']);
        $this->assertStringContainsString('beklenen alanları taşımıyor', $sonuc['ic_aciklama']);
    }

    /** Ortak kurulum: modeli taklit et, teklifi üret. */
    private function uret(string $talep, string $yanit = '{"musteri_maili":"a","ic_aciklama":"b"}'): array
    {
        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')->once()->andReturn($yanit);

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        return (new QuoteService($llm, $kb))->generateQuote($talep);
    }

    public function test_generate_teklif_narrows_price_items_by_keyword(): void
    {
        PriceItem::factory()->create(['category' => 'Backup', 'name' => 'Backup 1', 'aktif' => true]);
        PriceItem::factory()->create(['category' => 'Domain', 'name' => '.com', 'aktif' => true]);

        $llm = Mockery::mock(LlmService::class);
        $llm->shouldReceive('generateText')->once()->andReturn('{"musteri_maili":"a","ic_aciklama":"b"}');

        $kb = Mockery::mock(KnowledgeBaseService::class);
        $kb->shouldReceive('query')->once()->andReturn([]);

        $service = new QuoteService($llm, $kb);

        $sonuc = $service->generateQuote('backup yedekleme istiyorum');

        // "backup"/"yedek" anahtar kelimesi sadece Backup kategorisini eslemeli.
        $this->assertSame(1, $sonuc['kullanilan_fiyat_kalemi_sayisi']);
    }
}
