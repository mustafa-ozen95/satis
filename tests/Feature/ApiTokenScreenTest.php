<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeton ekranı: yalnızca yönetici, sır yalnızca bir kez.
 *
 * Jeton çıkarmak yetki dağıtmakla aynı ağırlıkta bir iştir; ekranın kendisi
 * de o ağırlığa göre korunuyor.
 */
class ApiTokenScreenTest extends TestCase
{
    use RefreshDatabase;

    private function jeton(?User $sahip = null, array $yetkiler = ['fiyat' => ['view']]): ApiToken
    {
        $sahip ??= User::factory()->rol('muhasebe')->create();
        $sir = ApiToken::mint();

        return ApiToken::create([
            'name' => 'deneme',
            'user_id' => $sahip->id,
            'token_hash' => $sir['hash'],
            'prefix' => $sir['prefix'],
            'abilities' => $yetkiler,
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_ekran_yalnizca_yoneticide(): void
    {
        $this->actingAs(User::factory()->rol('muhasebe')->create())
            ->get(route('admin.api-jetonlari.index'))
            ->assertRedirect(route('yetkisiz'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-jetonlari.index'))
            ->assertOk();
    }

    public function test_belge_uclari_listeler(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.api-jetonlari.belge'))
            ->assertOk()
            ->assertSee('/api/v1/fiyatlar')
            ->assertSee('fiyat:view')
            ->assertSee('Fiyat kalemleri · Görüntüle');
    }

    public function test_uretilen_sir_bir_kez_gosterilir_ve_ozeti_saklanir(): void
    {
        $yonetici = User::factory()->admin()->create();
        $sahip = User::factory()->rol('muhasebe')->create();

        $this->actingAs($yonetici)->post(route('admin.api-jetonlari.store'), [
            'name' => 'Site',
            'user' => $sahip->id,
            'abilities' => ['fiyat' => ['view']],
            'lifetime_days' => 30,
        ])->assertRedirect();

        $sir = session('token_plain');

        $this->assertNotEmpty($sir);
        $this->assertStringStartsWith('sys_', $sir);

        /* Veritabanında düz metin YOK, yalnızca özet. */
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $sir]);
        $this->assertDatabaseHas('api_tokens', [
            'token_hash' => hash('sha256', $sir),
            'user_id' => $sahip->id,
            'created_by' => $yonetici->id,
        ]);

        $jeton = ApiToken::query()->firstOrFail();

        /* Yönlendirmenin indiği sayfa: sır BURADA görünür. */
        $this->actingAs($yonetici)
            ->get(route('admin.api-jetonlari.show', $jeton))
            ->assertOk()
            ->assertSee($sir);

        /* Sayfa yenilendiğinde GİTMİŞ olmalı: oturumda tek seferlik durur. */
        $this->actingAs($yonetici)
            ->get(route('admin.api-jetonlari.show', $jeton))
            ->assertOk()
            ->assertDontSee($sir);
    }

    public function test_tanimsiz_yetki_kaydedilmez(): void
    {
        $yonetici = User::factory()->admin()->create();
        $sahip = User::factory()->rol('muhasebe')->create();

        $this->actingAs($yonetici)->post(route('admin.api-jetonlari.store'), [
            'name' => 'Site',
            'user' => $sahip->id,
            'abilities' => ['fiyat' => ['view', 'delete'], 'uydurma' => ['view']],
        ])->assertRedirect();

        $this->assertSame(['fiyat' => ['view']], ApiToken::query()->firstOrFail()->abilities);
    }

    public function test_sahibi_sonradan_degistirilemez(): void
    {
        $jeton = $this->jeton();
        $baskasi = User::factory()->rol('muhasebe')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.api-jetonlari.show', $jeton))
            ->put(route('admin.api-jetonlari.update', $jeton), [
                'name' => 'deneme',
                'user' => $baskasi->id,
            ])
            ->assertSessionHasErrors('user');

        $this->assertSame($jeton->user_id, $jeton->fresh()->user_id);
    }

    public function test_yetkiler_ve_adresler_guncellenir(): void
    {
        $jeton = $this->jeton();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.api-jetonlari.update', $jeton), [
                'name' => 'yeni ad',
                'abilities' => ['teklif' => ['view', 'create']],
                'allowed_ips' => "192.0.2.7\n198.51.100.0/24",
                'lifetime_days' => 0,
            ])
            ->assertSessionHasNoErrors();

        $jeton->refresh();

        $this->assertSame('yeni ad', $jeton->name);
        $this->assertSame(['teklif' => ['view', 'create']], $jeton->abilities);
        $this->assertSame(['192.0.2.7', '198.51.100.0/24'], $jeton->allowed_ips);

        /* 0 gün = süresiz; alanı boşaltmak yerine 0 yazmak daha doğal. */
        $this->assertNull($jeton->expires_at);
    }

    public function test_gecersiz_adres_reddedilir(): void
    {
        $jeton = $this->jeton();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.api-jetonlari.show', $jeton))
            ->put(route('admin.api-jetonlari.update', $jeton), [
                'name' => 'deneme',
                'allowed_ips' => 'sunucu.example',
            ])
            ->assertSessionHasErrors('allowed_ips.0');
    }

    public function test_yeni_sir_eskisini_gecersiz_kilar(): void
    {
        $sahip = User::factory()->rol('muhasebe')->create();
        $sir = ApiToken::mint();

        $jeton = ApiToken::create([
            'name' => 'deneme',
            'user_id' => $sahip->id,
            'token_hash' => $sir['hash'],
            'prefix' => $sir['prefix'],
            'abilities' => ['fiyat' => ['view']],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.api-jetonlari.sir', $jeton))
            ->assertRedirect();

        $yeni = session('token_plain');

        $this->assertNotEmpty($yeni);
        $this->assertNotSame($sir['plain'], $yeni);

        $baslik = ['Accept' => 'application/json'];
        $this->getJson('/api/v1/fiyatlar', $baslik + ['Authorization' => 'Bearer '.$sir['plain']])
            ->assertUnauthorized();
        $this->getJson('/api/v1/fiyatlar', $baslik + ['Authorization' => 'Bearer '.$yeni])
            ->assertOk();
    }

    public function test_iptal_silmez(): void
    {
        $jeton = $this->jeton();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.api-jetonlari.iptal', $jeton))
            ->assertRedirect();

        $this->assertNotNull($jeton->fresh()->revoked_at);
        $this->assertDatabaseHas('api_tokens', ['id' => $jeton->id]);
    }

    public function test_silme_kaydi_kaldirir(): void
    {
        $jeton = $this->jeton();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.api-jetonlari.destroy', $jeton))
            ->assertRedirect(route('admin.api-jetonlari.index'));

        $this->assertDatabaseMissing('api_tokens', ['id' => $jeton->id]);
    }

    public function test_muhasebeci_jeton_uretemez(): void
    {
        $muhasebeci = User::factory()->rol('muhasebe')->create();

        $this->actingAs($muhasebeci)->post(route('admin.api-jetonlari.store'), [
            'name' => 'Site',
            'user' => $muhasebeci->id,
            'abilities' => ['fiyat' => ['view']],
        ])->assertRedirect(route('yetkisiz'));

        $this->assertSame(0, ApiToken::query()->count());
    }
}
