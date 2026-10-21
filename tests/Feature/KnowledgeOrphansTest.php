<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KnowledgeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * YETİM VEKTÖRLER: Chroma'da olup hiçbir kayda ait olmayanlar.
 *
 * Aramaya karışıp modele silinmiş ya da eskimiş bilgi söyletiyorlar ve
 * ekranda görünmedikleri için kimse fark etmiyor; tur onları ayıklar.
 */
class KnowledgeOrphansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        config(['services.gemini.key' => 'deneme']);
    }

    /**
     * Bellekte küçük bir Chroma: belgeleri sayfa sayfa verir, silineni
     * düşer, yazılanı günceller.
     *
     * @param  list<array{id: string, metin?: ?string, ustveri?: ?array<string, mixed>}>  $belgeler
     */
    private function chroma(array $belgeler, int $gomme = 200, int $koleksiyonuSil = 200): void
    {
        Http::fake(function (Request $istek) use (&$belgeler, $gomme, $koleksiyonuSil) {
            $url = $istek->url();

            if (str_contains($url, 'embedContent')) {
                return Http::response(['embedding' => ['values' => [0.1, 0.2]]], $gomme);
            }

            if (str_ends_with($url, '/collections/bilgi_bankasi')) {
                return $istek->method() === 'DELETE'
                    ? Http::response([], $koleksiyonuSil)
                    : Http::response(['id' => 'kol-1']);
            }

            if (str_ends_with($url, '/collections/kol-1/get')) {
                $sayfa = array_slice($belgeler, (int) $istek['offset'], (int) $istek['limit']);

                return Http::response([
                    'ids' => array_column($sayfa, 'id'),
                    'documents' => in_array('documents', $istek['include'], true)
                        ? array_map(fn (array $b): ?string => $b['metin'] ?? null, $sayfa)
                        : null,
                    'metadatas' => array_map(fn (array $b): ?array => $b['ustveri'] ?? null, $sayfa),
                ]);
            }

            if (str_ends_with($url, '/collections/kol-1/delete')) {
                $belgeler = array_values(array_filter(
                    $belgeler,
                    fn (array $b): bool => ! in_array($b['id'], $istek['ids'], true),
                ));

                return Http::response(['deleted' => count($istek['ids'])]);
            }

            if (str_ends_with($url, '/collections/kol-1/upsert')) {
                foreach ($istek['ids'] as $i => $kimlik) {
                    $belgeler = array_values(array_filter($belgeler, fn (array $b): bool => $b['id'] !== $kimlik));
                    $belgeler[] = ['id' => $kimlik, 'metin' => $istek['documents'][$i], 'ustveri' => $istek['metadatas'][$i]];
                }

                return Http::response([]);
            }

            return Http::response([]);
        });
    }

    /** @return list<string> silinmesi istenen bütün vektör kimlikleri, sırayla */
    private function silinenler(): array
    {
        return Http::recorded(fn (Request $istek): bool => str_ends_with($istek->url(), '/collections/kol-1/delete'))
            ->flatMap(fn (array $cift): array => $cift[0]['ids'])
            ->values()
            ->all();
    }

    private function kayit(string $baslik, string $kategori, string $kaynak = 'manuel'): KnowledgeRecord
    {
        return KnowledgeRecord::create([
            'baslik' => $baslik,
            'kategori' => $kategori,
            'metin' => "{$baslik} metni",
            'kaynak' => $kaynak,
            'chroma_id' => (string) Str::uuid(),
            'indekslendi' => now(),
        ]);
    }

    /** Vektör, kaydının bıraktığı üstveriyle. */
    private function vektoru(KnowledgeRecord $kayit): array
    {
        return ['id' => $kayit->chroma_id, 'metin' => $kayit->metin, 'ustveri' => [
            'title' => $kayit->baslik, 'category' => $kayit->kategori, 'source' => $kayit->kaynak, 'kayit_id' => (string) $kayit->id,
        ]];
    }

    /**
     * BİZİM YETİMİMİZ KENDİLİĞİNDEN SİLİNİR.
     *
     * deleteEntry Chroma erişilemezse vektörü bırakıyor; kayıt başka bir
     * kimlikle yeniden yazıldıysa eskisi de kalıyordu. Asıl veri
     * veritabanında: kaydın göstermediği vektör yalnızca yanlış bağlamdır.
     */
    public function test_kaydi_silinmis_ya_da_yeniden_yazilmis_vektor_silinir(): void
    {
        $kayit = $this->kayit('Kabin Ölçüleri', 'Alan');
        $silinmis = (string) Str::uuid();
        $eskisi = (string) Str::uuid();

        $this->chroma([
            $this->vektoru($kayit),
            ['id' => $silinmis, 'metin' => 'silinen', 'ustveri' => ['title' => 'Silinen', 'kayit_id' => '999']],
            ['id' => $eskisi, 'metin' => 'eski', 'ustveri' => ['title' => 'Kabin Ölçüleri', 'kayit_id' => (string) $kayit->id]],
        ]);

        $this->artisan('bilgi:indeksle')->assertSuccessful();

        $this->assertSame([$silinmis, $eskisi], $this->silinenler());
    }

    /**
     * O AN İNDEKSLENEN KAYDIN VEKTÖRÜ SİLİNMEZ.
     *
     * Kayıt eklenirken önce satır, sonra vektör, en son satıra kimlik
     * yazılır. Arada bakan tur vektörü "kaydının kimliği başka" sanmamalı.
     */
    public function test_kimligi_henuz_yazilmamis_kaydin_vektoru_silinmez(): void
    {
        $kayit = KnowledgeRecord::create(['baslik' => 'Yeni', 'kategori' => 'Alan', 'metin' => 'metin']);

        /* Gömme alınamıyor (anahtar geçersiz): kayıt bu turda da kimliksiz kalır. */
        $this->chroma([
            ['id' => (string) Str::uuid(), 'metin' => 'metin', 'ustveri' => ['title' => 'Yeni', 'kayit_id' => (string) $kayit->id]],
        ], gomme: 400);

        $this->artisan('bilgi:indeksle')->assertFailed();

        $this->assertSame([], $this->silinenler());
    }

    /**
     * DIŞARIDAN YAZILMIŞ YETİME KENDİLİĞİNDEN DOKUNULMAZ.
     *
     * İçinde veritabanında olmayan bilgi olabilir (elle yazılmış ürün notları
     * gibi). Tur başarısız döner ve yolu gösterir; kararı insan verir.
     */
    public function test_disaridan_yazilmis_yetim_tur_basarisiz_doner_dokunulmaz(): void
    {
        $this->chroma([
            ['id' => (string) Str::uuid(), 'metin' => 'Standart olarak 2 adet disk.', 'ustveri' => [
                'title' => 'Örnek Sunucu A1 — Ek Disk ve RAID', 'category' => 'Dedicated Sunucu', 'source' => 'panel-manuel',
            ]],
        ]);

        $this->artisan('bilgi:indeksle')
            ->expectsOutputToContain('Örnek Sunucu A1')
            ->expectsOutputToContain('bilgi:indeksle --yetimler')
            ->assertFailed();

        $this->assertSame([], $this->silinenler());
        $this->assertSame(0, KnowledgeRecord::query()->count());
    }

    /**
     * --yetimler: dışarıdan yazılmış yetimlerin üç türü.
     *
     * Sitenin eski metni silinir (güncel hâli tarayıcının kaydında), eşi
     * veritabanında olan silinir, başka yerde olmayan bilgi içeri alınır ve
     * aynı kimlikle, kayit_id taşıyarak yeniden yazılır.
     */
    public function test_yetimler_kopyalari_siler_benzersizi_iceri_alir(): void
    {
        config(['company.site' => 'https://www.ornek.test']);

        $site = $this->kayit('Gizlilik — Toplanan Bilgiler', 'Genel', 'ornek.test');
        $elle = $this->kayit('Kabin Ölçüleri', 'Alan');

        $siteninEskisi = (string) Str::uuid();
        $elleninEsi = (string) Str::uuid();
        $benzersiz = (string) Str::uuid();
        $metinsiz = (string) Str::uuid();

        $this->chroma([
            $this->vektoru($site),
            $this->vektoru($elle),
            /* Sitenin o günkü metni; sayfanın başlıkları sonradan değişti. */
            ['id' => $siteninEskisi, 'metin' => 'eski site metni', 'ustveri' => [
                'title' => 'Gizlilik — 1. Toplanan Bilgiler', 'category' => 'Genel', 'source' => 'ornek.test',
            ]],
            ['id' => $elleninEsi, 'metin' => 'eski ölçüler', 'ustveri' => [
                'title' => 'Kabin Ölçüleri', 'category' => 'Alan', 'source' => 'panel-manuel',
            ]],
            ['id' => $benzersiz, 'metin' => 'Standart olarak 2 adet 500GB SATA disk kuruludur.', 'ustveri' => [
                'title' => 'Örnek Sunucu A1 — Ek Disk ve RAID', 'category' => 'Dedicated Sunucu', 'source' => 'panel-manuel',
            ]],
            ['id' => $metinsiz, 'metin' => null, 'ustveri' => null],
        ]);

        $this->artisan('bilgi:indeksle', ['--yetimler' => true])->assertSuccessful();

        $this->assertSame([$siteninEskisi, $elleninEsi, $metinsiz], $this->silinenler());

        $alinan = KnowledgeRecord::query()->where('chroma_id', $benzersiz)->firstOrFail();
        $this->assertSame('Örnek Sunucu A1 — Ek Disk ve RAID', $alinan->baslik);
        $this->assertSame('Dedicated Sunucu', $alinan->kategori);
        $this->assertSame('panel-manuel', $alinan->kaynak);
        $this->assertNotNull($alinan->indekslendi, 'İçeri alınan kayıt aynı turda indekslenmeli.');

        Http::assertSent(fn (Request $istek): bool => str_ends_with($istek->url(), '/upsert')
            && $istek['ids'] === [$benzersiz]
            && $istek['metadatas'][0]['kayit_id'] === (string) $alinan->id);

        /* İkinci tur bir şey değiştirmez: alınan kayıt artık bağlı. */
        $this->artisan('bilgi:indeksle', ['--yetimler' => true])->assertSuccessful();
        $this->assertSame(3, KnowledgeRecord::query()->count());
        $this->assertCount(3, $this->silinenler());
    }

    /** Koleksiyon büyüse de hepsi okunur; silme de parça parça gider. */
    public function test_sayfa_sayfa_okur_parca_parca_siler(): void
    {
        $yetimler = array_map(
            fn (int $i): array => ['id' => (string) Str::uuid(), 'ustveri' => ['kayit_id' => (string) (1000 + $i)]],
            range(1, 201),
        );

        $this->chroma($yetimler);

        $this->artisan('bilgi:indeksle')->assertSuccessful();

        $this->assertSame(array_column($yetimler, 'id'), $this->silinenler());
        $this->assertCount(2, Http::recorded(fn (Request $istek): bool => str_ends_with($istek->url(), '/collections/kol-1/get')));
        $this->assertCount(2, Http::recorded(fn (Request $istek): bool => str_ends_with($istek->url(), '/collections/kol-1/delete')));
    }

    /** Chroma okunamıyorsa tur "tamam" demez. */
    public function test_chroma_okunamazsa_tur_basarisiz_doner(): void
    {
        Http::fake(['*' => Http::response('bozuk', 500)]);

        $this->artisan('bilgi:indeksle')
            ->expectsOutputToContain('Chroma okunamadı')
            ->assertFailed();
    }

    /**
     * SIFIRLAMA SİLEMEZSE KİMLİKLERE DOKUNMAZ.
     *
     * Silme hatası yutulup kimlikler yine boşaltılsaydı koleksiyon yerinde
     * kalır, her kayıt ikinci kez yazılır, eskiler yetim kalırdı.
     */
    public function test_sifirlama_koleksiyon_silinemezse_kayitlara_dokunmaz(): void
    {
        $kayit = $this->kayit('Kabin Ölçüleri', 'Alan');

        $this->chroma([$this->vektoru($kayit)], koleksiyonuSil: 500);

        $this->artisan('bilgi:indeksle', ['--sifirla' => true])
            ->expectsOutputToContain('İndeks silinemedi')
            ->assertFailed();

        $this->assertSame($kayit->chroma_id, $kayit->fresh()->chroma_id);
    }

    /** Koleksiyon zaten yoksa (404) silinecek bir şey yok: baştan kurulur. */
    public function test_sifirlama_koleksiyon_yoksa_bastan_kurar(): void
    {
        $kayit = $this->kayit('Kabin Ölçüleri', 'Alan');

        $this->chroma([], koleksiyonuSil: 404);

        $this->artisan('bilgi:indeksle', ['--sifirla' => true])->assertSuccessful();

        $yeni = $kayit->fresh();
        $this->assertNotNull($yeni->chroma_id);
        $this->assertNotSame($kayit->chroma_id, $yeni->chroma_id);
        $this->assertNotNull($yeni->indekslendi);
    }
}
