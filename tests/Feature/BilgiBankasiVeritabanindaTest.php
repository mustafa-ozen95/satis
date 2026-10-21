<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BilgiKaydi;
use App\Models\User;
use App\Services\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BİLGİ BANKASININ ASLI VERİTABANINDA.
 *
 * Asıl veri veritabanında: yedeğe girer, Chroma düşünce ekran ayakta kalır,
 * düz metin araması yapılır. Chroma ondan türetilen indekstir.
 */
class BilgiBankasiVeritabanindaTest extends TestCase
{
    use RefreshDatabase;

    private function chromaCalisiyor(): void
    {
        Http::fake([
            '*/collections/bilgi_bankasi' => Http::response(['id' => 'kol-1']),
            '*/collections/kol-1/count' => Http::response('3'),
            '*/collections/kol-1/query' => Http::response([
                'documents' => [['Kabin 90x60']],
                'metadatas' => [[['title' => 'Kabin', 'category' => 'Alan']]],
                'distances' => [[0.1]],
            ]),
            '*/collections/kol-1/upsert' => Http::response([]),
            '*/collections/kol-1/delete' => Http::response([]),
            '*embedContent*' => Http::response(['embedding' => ['values' => [0.1, 0.2]]]),
            '*' => Http::response([]),
        ]);

        config(['services.gemini.key' => 'deneme']);
    }

    public function test_koleksiyon_adi_degismez(): void
    {
        /*
         * Var olan kayıtlar bu adın altında durur. Ad değişirse uygulama
         * sessizce BOŞ bir koleksiyon yaratıp onu kullanır ve bilgi bankası
         * boş görünür.
         */
        $this->assertStringContainsString(
            "'bilgi_bankasi'",
            file_get_contents(app_path('Services/KnowledgeBaseService.php')),
        );
    }

    public function test_kayit_veritabanina_yazilir_ve_indekslenir(): void
    {
        $this->chromaCalisiyor();

        $id = app(KnowledgeBaseService::class)->addEntry('Kabinler 90x60', 'Kabin', 'Alan');

        $this->assertDatabaseHas('bilgi_kayitlari', ['id' => $id, 'baslik' => 'Kabin', 'metin' => 'Kabinler 90x60']);
        $this->assertNotNull(BilgiKaydi::find($id)->chroma_id, 'indeks kimliği yazılmalı');
    }

    public function test_chroma_dusukken_kayit_yine_durur(): void
    {
        /*
         * İndeks yazılamazsa kayıt KAYBOLMAZ; yalnızca indekslenmemiş kalır
         * ve `bilgi:indeksle` onu sonradan yakalar.
         */
        Http::fake(['*' => Http::response('bozuk', 500)]);
        config(['services.gemini.key' => 'deneme']);

        $id = app(KnowledgeBaseService::class)->addEntry('Kural', 'Başlık', 'Kategori');

        $this->assertDatabaseHas('bilgi_kayitlari', ['id' => $id]);
        $this->assertNull(BilgiKaydi::find($id)->indekslendi);
        $this->assertSame(1, BilgiKaydi::query()->indekslenmemis()->count());
    }

    public function test_liste_dis_servise_sormaz(): void
    {
        BilgiKaydi::create(['baslik' => 'Bir', 'kategori' => 'Alan', 'metin' => 'metin']);

        Http::fake();

        $liste = app(KnowledgeBaseService::class)->listEntries();

        $this->assertCount(1, $liste);
        Http::assertNothingSent();
    }

    public function test_chroma_dusukken_arama_metne_duser(): void
    {
        BilgiKaydi::create([
            'baslik' => 'Kabin Ölçüleri',
            'kategori' => 'Ayrılmış Alan',
            'metin' => 'İki kabin tipi kullanılıyor: 90x60 ve 105x60.',
        ]);

        Http::fake(['*' => Http::response('yok', 500)]);
        config(['services.gemini.key' => 'deneme']);

        /* Anlamsal arama yapılamıyor ama teklif üretimi bağlamsız kalmıyor. */
        $sonuc = app(KnowledgeBaseService::class)->query('kabin ölçüleri nedir');

        $this->assertNotEmpty($sonuc);
        $this->assertStringContainsString('90x60', $sonuc[0]['text']);
    }

    public function test_silinen_kayit_veritabanindan_da_gider(): void
    {
        $this->chromaCalisiyor();

        $id = app(KnowledgeBaseService::class)->addEntry('Silinecek', 'Başlık', 'Kategori');
        app(KnowledgeBaseService::class)->deleteEntry($id);

        $this->assertDatabaseMissing('bilgi_kayitlari', ['id' => $id]);
    }

    public function test_ekranda_yonetici_kaydi_gorur(): void
    {
        BilgiKaydi::create(['baslik' => 'Görünsün', 'kategori' => 'Alan', 'metin' => 'metin']);

        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('bilgi-bankasi.index'))
            ->assertOk()
            ->assertSee('Görünsün');
    }

    /**
     * EKRANDA DOSYA ADI GÖRÜNMEZ.
     *
     * Kaynak alanında betik adı (`seed_notes.php`) yerine `kurulum` durur;
     * liste ham değeri değil etiketi basar.
     */
    public function test_ekranda_kaynak_dosya_adi_degil_etiket_yazar(): void
    {
        BilgiKaydi::create([
            'baslik' => 'Kabin',
            'kategori' => 'Alan',
            'metin' => 'metin',
            'kaynak' => 'kurulum',
        ]);

        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('bilgi-bankasi.index'))
            ->assertOk()
            ->assertSee('Kurulum seti')
            ->assertDontSee('seed_notes')
            ->assertDontSee('.php');
    }

    /**
     * KATEGORİ SEÇİLİR; YENİSİ İSTİSNADIR.
     *
     * Ekran listeden seçtiriyor. "+ Yeni kategori…" seçildiğinde gerçek
     * değer yanındaki kutudan gelir ve kayda o yazılır; kategori serbest
     * metin değil, seçimdir.
     */
    public function test_yeni_kategori_secilince_yandaki_kutudan_alinir(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('bilgi-bankasi.store'), [
                'baslik' => 'Yeni kayıt',
                'kategori' => '__yeni',
                'kategori_yeni' => 'Taşıma',
                'bilgi' => 'metin',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bilgi_kayitlari', ['baslik' => 'Yeni kayıt', 'kategori' => 'Taşıma']);
    }

    /** Yeni kategori seçilip kutu boş bırakılırsa kayıt olmaz. */
    public function test_bos_yeni_kategori_reddedilir(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('bilgi-bankasi.store'), [
                'baslik' => 'Yeni kayıt',
                'kategori' => '__yeni',
                'kategori_yeni' => '   ',
                'bilgi' => 'metin',
            ])
            ->assertSessionHasErrors('kategori');

        $this->assertDatabaseMissing('bilgi_kayitlari', ['baslik' => 'Yeni kayıt']);
    }

    /** Ekranda kategori kutusu SEÇİM olarak çizilir. */
    public function test_ekranda_kategori_secim_kutusu_var(): void
    {
        BilgiKaydi::create(['baslik' => 'Var', 'kategori' => 'Kurumsal', 'metin' => 'metin']);

        Http::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('bilgi-bankasi.index'))
            ->assertOk()
            ->assertSee('data-kategori-secim', false)
            ->assertSee('+ Yeni kategori…');
    }

    /** Göç eski değeri bırakmaz. */
    public function test_eski_kaynak_degeri_gocte_degisir(): void
    {
        $this->assertSame(
            0,
            BilgiKaydi::query()->where('kaynak', 'like', '%.php')->count(),
            'Kaynak alanında dosya adı kalmamalı.',
        );
    }
}
