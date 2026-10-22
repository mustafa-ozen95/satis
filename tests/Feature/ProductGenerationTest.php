<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PriceItem;
use App\Services\KnowledgeBaseService;
use App\Services\LlmService;
use App\Services\QuoteService;
use App\Support\ProductGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * ÜRÜN NESLİ MODELE SÖYLENİR.
 *
 * Nesli ürün adından çıkarmayı modele bırakmak, çıkarımı doğru yapacağını
 * ummak olurdu. Bilgi VERİLİR: her kalemin nesli ve o kategorideki en yeni
 * neslin hangisi olduğu fiyat listesinde yazılı gider.
 */
class ProductGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_addaki_nesil_okunur(): void
    {
        $this->assertSame(9, ProductGeneration::generation('HP ProLiant DL160-Gen9 (6C/12T, 48GB RAM DDR4)'));
        $this->assertSame(8, ProductGeneration::generation('HP ProLiant DL320e-Gen8 v2 (4C/8T, 8GB RAM)'));
        $this->assertSame(7, ProductGeneration::generation('HP ProLiant ML110-G7 ATX (4C, 8GB RAM)'));
    }

    /**
     * NESLİ OLMAYAN KALEM YOK SAYILIR.
     *
     * "8GB", "1Gbps", "250GB" gibi yazımlar nesil değildir; uydurma bir
     * "Nesil:" satırı modelin kafasını karıştırırdı.
     */
    public function test_nesil_olmayan_ad_bos_doner(): void
    {
        $this->assertNull(ProductGeneration::generation('IP Tahsis /29 (8 adet)'));
        $this->assertNull(ProductGeneration::generation('Sunucu 8GB RAM 1Gbps 250GB SATA'));
        $this->assertNull(ProductGeneration::generation(null));
    }

    /** Fiyat listesinde EN YENİ NESİL işaretlenir. */
    public function test_fiyat_listesinde_en_yeni_nesil_isaretlenir(): void
    {
        PriceItem::factory()->create([
            'category' => 'Dedicated Sunucu',
            'name' => 'HP ProLiant DL320e-Gen8 (16GB RAM)',
        ]);

        PriceItem::factory()->create([
            'category' => 'Dedicated Sunucu',
            'name' => 'HP ProLiant DL160-Gen9 (48GB RAM DDR4)',
        ]);

        $istem = $this->istem('16 GB ram yeni nesil sunucu öner');

        $this->assertStringContainsString('Nesil: Gen9 — bu kategorideki EN YENİ nesil', $istem);
        $this->assertStringContainsString('Nesil: Gen8 — daha yenisi var (Gen9)', $istem);
    }

    /**
     * KARŞILAŞTIRMA KATEGORİ İÇİNDE.
     *
     * Başka kategorideki bir numarayla karşılaştırmak, bir sunucuyu bir
     * lisansın sürümüyle kıyaslamak olurdu.
     */
    public function test_nesil_kategori_icinde_karsilastirilir(): void
    {
        PriceItem::factory()->create(['category' => 'Dedicated Sunucu', 'name' => 'DL320e-Gen8']);
        PriceItem::factory()->create(['category' => 'Bulut Sunucu', 'name' => 'Bulut Gen9']);

        $istem = $this->istem('sunucu öner');

        $this->assertStringContainsString('Nesil: Gen8 — bu kategorideki EN YENİ nesil', $istem);
    }

    /** Kural da istemde durur: veri tek başına yetmiyor. */
    public function test_nesil_kurali_istemde_yazar(): void
    {
        PriceItem::factory()->create(['category' => 'Dedicated Sunucu', 'name' => 'DL160-Gen9']);

        $this->assertStringContainsString('NESİL UYDURULMAZ', $this->istem('sunucu'));
    }

    /** Modele giden istemi yakalar. */
    private function istem(string $talep): string
    {
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

        (new QuoteService($llm, $kb))->generateQuote($talep);

        return $yakalanan;
    }
}
