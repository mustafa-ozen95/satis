<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PriceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fiyat hücresi YERİNDE düzenlenir.
 *
 * Tek alan gönderilir; tam form bütün alanları zorunlu kıldığı için yalnızca
 * fiyatı değiştirmek kategoriyi, adı ve birimi de göndermeyi gerektirirdi ve
 * o değerler ekrandan geldiği için eski hâllerini geri yazma riski taşırdı.
 */
class FiyatYerindeDuzenlemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_hucreler_duzenlenebilir_isaretli(): void
    {
        $kalem = PriceItem::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('fiyatlar.index'))
            ->assertOk()
            ->assertSee('data-alan="price"', false)
            ->assertSee('data-adres="'.route('fiyatlar.alan', $kalem).'"', false);
    }

    public function test_tek_alan_guncellenir(): void
    {
        $kalem = PriceItem::factory()->create(['price' => 100, 'name' => 'Eski ad', 'currency' => 'TL']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('fiyatlar.alan', $kalem), ['alan' => 'price', 'deger' => '250.50'])
            ->assertOk()
            ->assertJson(['ham' => '250.50', 'gosterim' => '250,5 TL']);

        $this->assertSame('250.50', $kalem->fresh()->price);

        /* Diğer alanlara DOKUNULMAZ: tek alan gönderildi. */
        $this->assertSame('Eski ad', $kalem->fresh()->name);
    }

    public function test_ne_degistigi_kayda_gecer(): void
    {
        $kalem = PriceItem::factory()->create(['price' => 100]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('fiyatlar.alan', $kalem), ['alan' => 'price', 'deger' => '150']);

        $satir = ActivityLog::where('tur', 'fiyat_guncelle')->latest('id')->first();

        $this->assertNotNull($satir);
        $this->assertStringContainsString('price: 100.00 → 150.00', (string) $satir->aciklama);
    }

    public function test_listede_olmayan_alan_yazilamaz(): void
    {
        /*
         * Alan adı doğrudan modele yazılsaydı ekranda hiç görünmeyen
         * sütunlar da (aktif, notlar) bu uçtan değiştirilebilirdi.
         */
        $kalem = PriceItem::factory()->create(['aktif' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('fiyatlar.alan', $kalem), ['alan' => 'aktif', 'deger' => '0'])
            ->assertStatus(422);

        $this->assertTrue($kalem->fresh()->aktif);
    }

    public function test_gecersiz_deger_reddedilir(): void
    {
        $kalem = PriceItem::factory()->create(['price' => 100]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('fiyatlar.alan', $kalem), ['alan' => 'price', 'deger' => 'bedava'])
            ->assertStatus(422);

        $this->assertSame('100.00', $kalem->fresh()->price);
    }

    public function test_yetkisiz_kullanici_duzenleyemez(): void
    {
        $kalem = PriceItem::factory()->create(['price' => 100]);

        $this->actingAs(User::factory()->create(['perm_fiyat' => false]))
            ->postJson(route('fiyatlar.alan', $kalem), ['alan' => 'price', 'deger' => '1'])
            ->assertForbidden();

        $this->assertSame('100.00', $kalem->fresh()->price);
    }
}
