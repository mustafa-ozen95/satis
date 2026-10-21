<?php

namespace Tests\Feature;

use App\Models\KnowledgeRecord;
use Database\Seeders\GeneralTechnicalKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Genel teknik bilgi kayıtları YENİDEN ÇALIŞTIRILABİLİR olmalı.
 *
 * Her çalıştırmada kayıtları baştan eklemek, yanlış bir metni düzeltip
 * yeniden çalıştırınca bütün kayıtların bir kopyasını daha üretirdi.
 */
class GeneralTechnicalKnowledgeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* Chroma ve gömme çağrıları dışarı çıkmaz; indeksleme hatası kaydı durdurmaz. */
        Http::fake();
    }

    public function test_ikinci_calistirma_kopya_uretmez_eski_metni_duzeltir(): void
    {
        $this->seed(GeneralTechnicalKnowledgeSeeder::class);

        $kayitlar = KnowledgeRecord::query()->where('kaynak', 'egitim-genel');
        $sayi = $kayitlar->count();
        $this->assertSame($sayi, $kayitlar->distinct()->count('baslik'));

        /* Daha önce girmiş yanlış metinler. */
        KnowledgeRecord::query()->where('baslik', 'RAID Seviyeleri')->update(['metin' => 'RAID 5: kapasite kaybı (n-1)/n oranındadır.']);
        KnowledgeRecord::query()->where('baslik', 'like', 'Domain Uzantı%')->update(['metin' => '.tr uzantıları ODTÜ Nic.tr üzerinden yönetilir.']);
        $dokunulmayan = KnowledgeRecord::query()->where('baslik', 'ECC RAM Nedir')->firstOrFail();

        $this->travel(5)->minutes();
        $this->seed(GeneralTechnicalKnowledgeSeeder::class);

        $this->assertSame($sayi, KnowledgeRecord::query()->where('kaynak', 'egitim-genel')->count());
        $this->assertStringContainsString('kullanılabilir alan (n-1)/n', KnowledgeRecord::query()->where('baslik', 'RAID Seviyeleri')->value('metin'));
        $this->assertStringContainsString('TRABİS', KnowledgeRecord::query()->where('baslik', 'like', 'Domain Uzantı%')->value('metin'));

        /* Metni değişmeyen kayda dokunulmaz: gömme ücretli bir çağrı. */
        $this->assertEquals($dokunulmayan->updated_at, $dokunulmayan->fresh()->updated_at);
    }
}
