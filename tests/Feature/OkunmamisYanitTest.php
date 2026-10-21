<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Listede üç hâl AYRI görünür: çalışıyor, okunmamış yanıt, okunmuş.
 *
 * Kullanıcı teklif isteyip başka işe geçer. Metin hazır olduğunda listede
 * bir işaret olmazsa yanıtın geldiği ancak konuşma açılınca fark edilir.
 */
class OkunmamisYanitTest extends TestCase
{
    use RefreshDatabase;

    private function konusma(User $sahip, string $durum = 'hazir'): Conversation
    {
        $konusma = $sahip->conversations()->create(['ilk_talep' => 'Kabin fiyatı', 'durum' => $durum]);
        $konusma->messages()->create(['role' => 'user', 'content' => 'Kabin fiyatı']);

        if ($durum === 'hazir') {
            $konusma->messages()->create(['role' => 'assistant', 'content' => 'Teklif metni']);
        }

        return $konusma;
    }

    public function test_hazir_ama_acilmamis_konusma_okunmamis_sayilir(): void
    {
        $konusma = $this->konusma(User::factory()->admin()->create());

        $this->assertTrue($konusma->okunmamisMi());
    }

    /** ÜRETİLİYORKEN okunmamış değildir: orada beklenen bir yanıt yok. */
    public function test_uretilen_konusma_okunmamis_sayilmaz(): void
    {
        $konusma = $this->konusma(User::factory()->admin()->create(), 'bekliyor');

        $this->assertFalse($konusma->okunmamisMi());
    }

    public function test_konusma_acilinca_okundu_isaretlenir(): void
    {
        $kullanici = User::factory()->admin()->create();
        $konusma = $this->konusma($kullanici);

        $this->actingAs($kullanici)->get(route('teklif.show', $konusma))->assertOk();

        $this->assertFalse($konusma->fresh()->okunmamisMi());
    }

    /**
     * SONRAKİ YANIT YİNE OKUNMAMIŞTIR.
     *
     * "Okundu" bayrağı olsaydı aynı konuşmaya gelen ikinci yanıt sessizce
     * okunmuş sayılırdı; ölçüt zaman olduğu için ayırt ediliyor.
     */
    public function test_yeni_yanit_geldiginde_yeniden_okunmamis_olur(): void
    {
        $kullanici = User::factory()->admin()->create();
        $konusma = $this->konusma($kullanici);

        $this->actingAs($kullanici)->get(route('teklif.show', $konusma));
        $this->assertFalse($konusma->fresh()->okunmamisMi());

        $this->travel(5)->minutes();
        $konusma->messages()->create(['role' => 'assistant', 'content' => 'İkinci yanıt']);

        $this->assertTrue($konusma->fresh()->okunmamisMi());
    }

    /** Ekranda üç hâl de kendi işaretiyle çizilir. */
    public function test_ekranda_calisiyor_ve_okunmamis_isaretleri_gorunur(): void
    {
        $kullanici = User::factory()->admin()->create();
        $this->konusma($kullanici);
        $this->konusma($kullanici, 'bekliyor');

        $this->actingAs($kullanici)
            ->get(route('teklif.create'))
            ->assertOk()
            ->assertSee('Hazırlanıyor…')
            ->assertSee('Yeni yanıt')
            ->assertSee('dot--calisiyor', false)
            ->assertSee('teklif-baglanti--okunmamis', false);
    }
}
