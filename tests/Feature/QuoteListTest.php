<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ConversationController;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEKLİF LİSTESİ: kime ait, ne kadarı, nasıl devam ediyor.
 *
 * Liste KİŞİYE ÖZELDİR: teklif metni müşteri yazışmasıdır ve iç açıklama
 * satış notu taşır. YÖNETİCİ HEPSİNİ GÖRÜR: panelin sorumlusu odur ve
 * "kim ne istemiş" sorusunu ancak tam listeden yanıtlar.
 *
 * Bir seferde ON BEŞ satır gelir; liste zamanla binlerce satıra çıkabilir
 * ve hepsini tek seferde çizmek telefonda sayfayı kilitler.
 */
class QuoteListTest extends TestCase
{
    use RefreshDatabase;

    private function teklif(User $sahip, string $baslik): Conversation
    {
        return $sahip->conversations()->create(['ilk_talep' => $baslik, 'durum' => 'hazir']);
    }

    public function test_kullanici_yalnizca_kendi_tekliflerini_gorur(): void
    {
        $ben = User::factory()->create();
        $baskasi = User::factory()->create();

        $this->teklif($ben, 'BENİM TEKLİFİM');
        $this->teklif($baskasi, 'BAŞKASININ TEKLİFİ');

        $this->actingAs($ben)
            ->get(route('quote.create'))
            ->assertOk()
            ->assertSee('BENİM TEKLİFİM')
            ->assertDontSee('BAŞKASININ TEKLİFİ');
    }

    public function test_yonetici_herkesin_teklifini_gorur(): void
    {
        $yonetici = User::factory()->admin()->create();
        $baskasi = User::factory()->create(['name' => 'Deneme Kişisi']);

        $this->teklif($baskasi, 'BAŞKASININ TEKLİFİ');

        $this->actingAs($yonetici)
            ->get(route('quote.create'))
            ->assertOk()
            ->assertSee('BAŞKASININ TEKLİFİ')
            /* Kimin istediği de görünür; yoksa "kim ne istemiş" yanıtsız kalır. */
            ->assertSee('Deneme');
    }

    /** Bir seferde ON BEŞ satır çizilir. */
    public function test_ilk_cizimde_on_bes_satir_gelir(): void
    {
        $kullanici = User::factory()->create();

        for ($i = 1; $i <= 20; $i++) {
            $this->teklif($kullanici, "TEKLİF-{$i}");
        }

        $yanit = $this->actingAs($kullanici)->get(route('quote.create'))->assertOk();

        /* En yeniler üstte: 20…6 gelir, 5 ve öncesi gelmez. */
        $yanit->assertSee('TEKLİF-20')->assertSee('TEKLİF-6')->assertDontSee('TEKLİF-5');
        $yanit->assertSee('Devamını gör');
    }

    /** On beşten az teklifte "Devamını gör" hiç çizilmez. */
    public function test_az_teklifte_devam_dugmesi_yok(): void
    {
        $kullanici = User::factory()->create();
        $this->teklif($kullanici, 'TEK TEKLİF');

        $this->actingAs($kullanici)
            ->get(route('quote.create'))
            ->assertOk()
            ->assertDontSee('Devamını gör');
    }

    /** "Devamını gör" sonraki on beşi verir. */
    public function test_devami_sonraki_on_besi_verir(): void
    {
        $kullanici = User::factory()->create();

        for ($i = 1; $i <= 20; $i++) {
            $this->teklif($kullanici, "TEKLİF-{$i}");
        }

        $altinci = Conversation::where('ilk_talep', 'TEKLİF-6')->firstOrFail();

        $this->actingAs($kullanici)
            ->get(route('quote.more', ['oncesi' => $altinci->id]))
            ->assertOk()
            ->assertSee('TEKLİF-5')
            ->assertSee('TEKLİF-1')
            ->assertDontSee('TEKLİF-6');
    }

    /**
     * DEVAM UCU DA KİŞİYE ÖZEL.
     *
     * Sorgu aynı yerden kuruluyor; ayrı yazılsaydı bir gün unutulur ve uç,
     * listenin arkasından herkesin teklifini verirdi.
     */
    public function test_devam_ucu_baskasinin_teklifini_vermez(): void
    {
        $ben = User::factory()->create();
        $baskasi = User::factory()->create();

        $this->teklif($baskasi, 'BAŞKASININ TEKLİFİ');
        $sonuncu = $this->teklif($ben, 'BENİM TEKLİFİM');

        $this->actingAs($ben)
            ->get(route('quote.more', ['oncesi' => $sonuncu->id + 100]))
            ->assertOk()
            ->assertDontSee('BAŞKASININ TEKLİFİ');
    }

    /** Sayfa boyu tek yerde tanımlı. */
    public function test_sayfa_boyu_on_bes(): void
    {
        $this->assertSame(15, ConversationController::SAYFA_BOYU);
    }
}
